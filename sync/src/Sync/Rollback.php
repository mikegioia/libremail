<?php

namespace App\Sync;

use App\Model;
use League\CLImate\CLImate;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class Rollback
{
    private $cli;

    public function __construct(CLImate $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Rolls all current logged tasks in the tasks table. Start
     * from the end and revert each one.
     *
     * @throws PDOException
     */
    public function run()
    {
        $count = 0;
        $taskModel = new TaskModel;
        $messageModel = new MessageModel;
        $this->cli->info('Starting rollback');
        $tasks = $taskModel->getTasksForRollback();

        if (! $tasks) {
            $this->cli->whisper('No tasks to roll back');

            return;
        }

        Model::getDb()->beginTransaction();

        try {
            foreach ($tasks as $task) {
                if ($this->revertAction($task, $messageModel)) {
                    $task->revert();
                    ++$count;
                }
            }
        }
        catch (Exception $e) {
            $this->cli->whisper(
                'Problem during rollback, rolling back the rollback :P');
            Model::getDb()->rollBack();

            throw new PDOException($e);
        }

        Model::getDb()->commit();

        $this->cli->info(
            "Finished rolling back $count task".
            (1 === $count ? '' : 's'));
    }

    /**
     * Reverts a message to it's previous state.
     *
     * @param TaskModel $task
     * @param MessageModel $message
     */
    private function revertAction(TaskModel $task, MessageModel $message)
    {
        switch ($task->type) {
            case TaskModel::TYPE_READ:
            case TaskModel::TYPE_UNREAD:
                $message->setFlag(
                    $task->message_id,
                    MessageModel::FLAG_SEEN,
                    $task->old_value);

                return true;

            case TaskModel::TYPE_FLAG:
            case TaskModel::TYPE_UNFLAG:
                $message->setFlag(
                    $task->message_id,
                    MessageModel::FLAG_FLAGGED,
                    $task->old_value);

                return true;

            case TaskModel::TYPE_DELETE:
            case TaskModel::TYPE_UNDELETE:
                $message->setFlag(
                    $task->message_id,
                    MessageModel::FLAG_DELETED,
                    $task->old_value);

                return true;

            case TaskModel::TYPE_COPY:
                // Mark as deleted any messages with this message-id
                // that are in the specified folder and that do not have
                // a unique ID field.
                $message->deleteCopiedMessages(
                    $task->message_id,
                    $task->folder_id);

                return true;
        }
    }
}
