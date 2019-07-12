<?php

/**
 * Syncs actions to the IMAP server.
 */

namespace App\Sync;

// Core
use Fn;
use Exception;
use RuntimeException;
// Vendor
use Monolog\Logger;
use Pb\Imap\Mailbox;
use Pb\Imap\Message;
use League\CLImate\CLImate;
use Evenement\EventEmitter as Emitter;
// Application
use App\Sync;
use App\Model;
use App\Model\Task as TaskModel;
use App\Model\Folder as FolderModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Account as AccountModel;
use App\Model\Message as MessageModel;
use App\Exceptions\NotFound as NotFoundException;

class Actions
{
    private $log;
    private $cli;
    private $emitter;
    private $mailbox;
    private $progress;
    private $interactive;
    private $foldersToExpunge = [];

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
        TaskModel::TYPE_DELETE_OUTBOX => 'App\Sync\Actions\DeleteOutbox',
        TaskModel::TYPE_FLAG => 'App\Sync\Actions\Flag',
        TaskModel::TYPE_READ => 'App\Sync\Actions\Read',
        TaskModel::TYPE_SEND => 'App\Sync\Actions\Send',
        TaskModel::TYPE_UNDELETE => 'App\Sync\Actions\Undelete',
        TaskModel::TYPE_UNFLAG => 'App\Sync\Actions\Unflag',
        TaskModel::TYPE_UNREAD => 'App\Sync\Actions\Unread'
    ];

    const ERR_FAIL_IMAP_SYNC = 'Failed IMAP sync';
    const ERR_NO_IMAP_MESSAGE = 'No message found in IMAP mailbox';
    const ERR_NO_SQL_FOLDER = 'No folder found in SQL';
    const ERR_NO_SQL_MESSAGE = 'No message found in SQL';
    const ERR_NO_SQL_OUTBOX = 'No outbox message found in SQL';
    const ERR_NO_SQL_SENT = 'No sent folder found in SQL';
    const ERR_TRANSPORT_FAIL = 'Message failed before transport';
    const ERR_TRANSPORT_GENERAL = 'Message failed from an unknown exception';

    const IGNORE_OUTBOX_DELETED = 'Outbox message previously deleted';
    const IGNORE_OUTBOX_SENT = 'Outbox message previously sent';

    public function __construct(
        Logger $log,
        CLImate $cli,
        Emitter $emitter,
        Mailbox $mailbox = null,
        bool $interactive = false
    ) {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->mailbox = $mailbox;
        $this->interactive = $interactive;
    }

    /**
     * Runs any available actions.
     *
     * @return int Count of actions processed
     */
    public function run(AccountModel $account)
    {
        $tasks = (new TaskModel)->getTasksForSync($account->id);
        $count = count($tasks);

        if (! $count) {
            return 0;
        }

        $noun = Fn\plural('task', $count);
        $this->log->info("Syncing $count $noun for {$account->email}");

        // Compress them by removing any redundant tasks
        // Redundant tasks are marked as `ignored`
        $tasks = $this->processDuplicates($tasks);
        $count = 0;

        $this->startProgress(count($tasks));

        foreach ($tasks as $i => $task) {
            if ($this->processTask($task, $account)) {
                ++$count;
            }

            $this->updateProgress($i + 1);
        }

        $this->expungeFolders();

        return $count;
    }

    /**
     * Returns a count of the tasks to be synced.
     *
     * @return int Count of actions to be processed
     */
    public function getCountForProcessing(AccountModel $account)
    {
        $tasks = (new TaskModel)->getTasksForSync($account->id);
        $count = count($tasks);

        if (! $count) {
            return 0;
        }

        // Compress them by removing any redundant tasks
        // Redundant tasks are marked as `ignored`
        $tasks = $this->processDuplicates($tasks, false);

        return count($tasks);
    }

    /**
     * Iterates through the task list and removes any duplicates
     * or redundant tasks.
     *
     * @param array $allTasks
     *
     * @return array
     */
    private function processDuplicates(array $allTasks, bool $enableLog = true)
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

        if ($ignoreCount && true === $enableLog) {
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
     * @param AccountModel $account
     */
    private function processTask(TaskModel $task, AccountModel $account)
    {
        $imapMessage = new Message;
        $sqlFolder = new FolderModel;
        $sqlOutbox = new OutboxModel($task->outbox_id);
        $sqlMessage = new MessageModel($task->message_id);

        // Some tasks have no message_id and don't need to be
        // processed the same way. These interact solely with
        // outbox messages.
        if ($task->requiresImapMessage()) {
            // Variables will be updated upon success
            $loaded = $this->loadMessageData(
                $task, $sqlMessage, $sqlFolder, $imapMessage
            );

            if (! $loaded) {
                return false;
            }
        }

        try {
            $sqlOutbox->loadById();
        } catch (NotFoundException $e) {
            if ($task->requiresOutboxMessage()) {
                return $task->fail(self::ERR_NO_SQL_OUTBOX);
            }
        }

        $actionClass = self::$actionLookup[$task->type] ?? null;

        if (! $actionClass) {
            throw new RuntimeException(
                "Task type {$task->type} not found in action classes lookup"
            );
        }

        $action = new $actionClass(
            $task, $account,
            $sqlFolder, $sqlMessage, $sqlOutbox,
            $imapMessage
        );

        if (! $action->isReady()) {
            return false;
        }

        Model::getDb()->beginTransaction();

        try {
            $action->run($this->mailbox);
            $task->done();
        } catch (Exception $e) {
            Model::getDb()->rollBack();

            $this->log->warning(
                "Failed syncing IMAP action for task {$task->id}: ".
                $e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);

            if (! $task->isFailed()) {
                $task->fail(self::ERR_FAIL_IMAP_SYNC);
            }

            return false;
        }

        Model::getDb()->commit();

        if (TaskModel::TYPE_DELETE === $task->type) {
            $this->foldersToExpunge[] = $sqlFolder->name;
        }

        return true;
    }

    /**
     * Modifies the final three parameters and loads the respective
     * data into them. Returns false on error and true on success.
     * Data may be modified even on failure, as part of the data could
     * be loaded.
     *
     * @return bool
     */
    private function loadMessageData(
        TaskModel $task,
        MessageModel &$message,
        FolderModel &$folder,
        Message &$imapMessage
    ) {
        try {
            $message->loadById();
        } catch (NotFoundException $e) {
            return $task->fail(self::ERR_NO_SQL_MESSAGE);
        }

        try {
            $folder->id = $message->folder_id;
            $folder->loadById();
        } catch (NotFoundException $e) {
            return $task->fail(self::ERR_NO_SQL_FOLDER);
        }

        // Skip messages without a unique ID. These were added
        // during a copy command (etc) and do not have a
        // corresponding server message. They'll be purged after
        // the message is copied to the mailbox.
        if (! $message->unique_id) {
            $task->ignore();

            return false;
        }

        try {
            $this->mailbox->select($folder->name);
            $msgNo = $this->mailbox->getNumberByUniqueId($message->unique_id);
            $imapMessage = $this->mailbox->getMessage($msgNo);
        } catch (Exception $e) {
            $this->log->warning(
                "Failed downloading message for task {$task->id}: ".
                $e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);

            return $task->fail(self::ERR_NO_IMAP_MESSAGE, $e);
        }

        return true;
    }

    /**
     * If any messages were deleted, we need to issue an EXPUNGE
     * command to the folders containing those messages. That way,
     * we won't pull them down again on sync.
     */
    private function expungeFolders()
    {
        if (! $this->foldersToExpunge) {
            return;
        }

        $folders = array_unique($this->foldersToExpunge);

        try {
            foreach ($folders as $folder) {
                $this->mailbox->expunge($folder);
            }
        } catch (Exception $e) {
            $this->log->warning(
                'Failed expunging folder {folder}: '.$e->getMessage());
            $this->emitter->emit(Sync::EVENT_CHECK_CLOSED_CONN, [$e]);
        }
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
