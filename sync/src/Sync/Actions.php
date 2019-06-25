<?php

/**
 * Syncs actions to the IMAP server.
 */

namespace App\Sync;

use Fn;
use App\Sync;
use Exception;
use Monolog\Logger;
use Pb\Imap\Mailbox;
use Pb\Imap\Message;
use RuntimeException;
use League\CLImate\CLImate;
use App\Model\Task as TaskModel;
use App\Model\Folder as FolderModel;
use App\Model\Account as AccountModel;
use App\Model\Message as MessageModel;
use Evenement\EventEmitter as Emitter;

class Actions
{
    private $log;
    private $cli;
    private $emitter;
    private $mailbox;
    private $progress;
    private $interactive;

    private static $dupeLookup = [
        TaskModel::TYPE_DELETE => TaskModel::TYPE_UNDELETE,
        TaskModel::TYPE_FLAG => TaskModel::TYPE_UNFLAG,
        TaskModel::TYPE_READ => TaskModel::TYPE_UNREAD,
        TaskModel::TYPE_UNDELETE => TaskModel::TYPE_DELETE,
        TaskModel::TYPE_UNFLAG => TaskModel::TYPE_FLAG,
        TaskModel::TYPE_UNREAD => TaskModel::TYPE_READ
    ];

    // Lookup of actions to action classes
    private static $actionLookup = [
        TaskModel::TYPE_COPY => 'App\Sync\Actions\Copy',
        TaskModel::TYPE_DELETE => 'App\Sync\Actions\Delete',
        TaskModel::TYPE_FLAG => 'App\Sync\Actions\Flag',
        TaskModel::TYPE_READ => 'App\Sync\Actions\Read',
        TaskModel::TYPE_UNDELETE => 'App\Sync\Actions\Undelete',
        TaskModel::TYPE_UNFLAG => 'App\Sync\Actions\Unflag',
        TaskModel::TYPE_UNREAD => 'App\Sync\Actions\Unread'
    ];

    const ERR_FAIL_IMAP_SYNC = 'Failed IMAP sync';
    const ERR_NO_IMAP_MESSAGE = 'No message found in IMAP mailbox';
    const ERR_NO_SQL_FOLDER = 'No folder found in SQL';
    const ERR_NO_SQL_MESSAGE = 'No message found in SQL';

    public function __construct(
        Logger $log,
        CLImate $cli,
        Emitter $emitter,
        Mailbox $mailbox,
        bool $interactive
    ) {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->mailbox = $mailbox;
        $this->interactive = $interactive;
    }

    public function run(AccountModel $account)
    {
        $tasks = (new TaskModel)->getTasksForSync($account->id);
        $count = count($tasks);

        if (! $count) {
            return;
        }

        $noun = Fn\plural('task', $count);
        $this->log->info("Syncing $count $noun for {$account->email}");

        // Compress them by removing any redundant tasks
        // Redundant tasks are marked as `ignored`
        $tasks = $this->processDuplicates($tasks);

        $this->startProgress(count($tasks));

        foreach ($tasks as $i => $task) {
            $this->processTask($task);
            $this->updateProgress($i + 1);
        }
    }

    /**
     * Iterates through the task list and removes any duplicates
     * or redundant tasks.
     *
     * @param array $allTasks
     *
     * @return array
     */
    private function processDuplicates(array $allTasks)
    {
        $tasks = [];
        $ignoreCount = 0;
        $remaining = count($allTasks);

        while ($remaining > 0) {
            --$remaining;
            $next = array_shift($allTasks);
            $dupeType = self::$dupeLookup[$next->type] ?? null;

            if ($dupeType) {
                foreach ($allTasks as $i => $sibling) {
                    // Redundancy found, remove both
                    if ($sibling->type === $dupeType
                        && $next->message_id === $sibling->message_id
                    ) {
                        --$remaining;
                        $next->ignore();
                        $sibling->ignore();
                        $ignoreCount += 2;
                        unset($allTasks[$i]);
                        goto loopEnd;
                    }

                    // Dupe found, remove the latter
                    if ($next->type === $sibling->type
                        && $next->message_id === $sibling->message_id
                    ) {
                        --$remaining;
                        $sibling->ignore();
                        ++$ignoreCount;
                        unset($allTasks[$i]);
                    }
                }
            }

            $tasks[] = $next;

            loopEnd: // skip out of the loop
        }

        if ($ignoreCount) {
            $noun = Fn\plural('task', $ignoreCount);
            $this->log->info("Ignored $ignoreCount $noun");
        }

        return $tasks;
    }

    /**
     * Syncs a task with the IMAP server. The first fetches the
     * message from the mail server to see if the UID matches.
     * If not an error is logged and the task is skipped.
     *
     * @param TaskModel $task
     */
    private function processTask(TaskModel $task)
    {
        $sqlMessage = (new MessageModel)->getById($task->message_id);

        if (! $sqlMessage) {
            return $task->fail(self::ERR_NO_SQL_MESSAGE);
        }

        $sqlFolder = (new FolderModel)->getById($sqlMessage->folder_id);

        if (! $sqlFolder) {
            return $task->fail(self::ERR_NO_SQL_FOLDER);
        }

        // Skip messages without a message number. These were added during
        // a copy command and do not have a corresponding server message.
        // They'll be purged after the message is copied to the mailbox.
        if (! $sqlMessage->message_no) {
            return $task->ignore();
        }

        try {
            $this->mailbox->select($sqlFolder->name);
            $imapMessage = $this->mailbox->getMessage($sqlMessage->message_no);
        } catch (Exception $e) {
            $this->log->warning(
                "Failed downloading message for task {$task->id}: ".
                $e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);

            return $task->fail(self::ERR_NO_IMAP_MESSAGE);
        }

        // Check if the unique ID is the same; halt if not
        if (! Fn\intEq($imapMessage->uid, $sqlMessage->unique_id)) {
            return $task->fail(self::ERR_UID_MISMATCH);
        }

        $actionClass = self::$actionLookup[$task->type] ?? null;

        if (! $actionClass) {
            throw new RuntimeException(
                "Task type {$task->type} not found in action classes lookup"
            );
        }

        try {
            (new $actionClass(
                $task, $sqlFolder, $sqlMessage, $imapMessage
            ))->run($this->mailbox);
        } catch (Exception $e) {
            $this->log->warning(
                "Failed syncing IMAP action for task {$task->id}: ".
                $e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);

            return $task->fail(self::ERR_FAIL_IMAP_SYNC);
        }

        $task->done();
    }

    private function startProgress(int $total)
    {
        if ($this->interactive) {
            $noun = Fn\plural('task', $total);
            $this->progress = $this->cli->progress()->total($total);
            $this->cli->whisper("Syncing $total $noun");
        }
    }

    private function updateProgress(int $count)
    {
        if ($this->progress) {
            $this->progress->current($count);
        }
    }
}
