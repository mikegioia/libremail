<?php

namespace App\Sync;

use App\Model;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use League\CLImate\CLImate;

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
        $this->cli->info('Starting rollback');
        $tasks = $taskModel->getTasksForRollback();

        if (! $tasks) {
            $this->cli->whisper('No tasks to roll back');

            return;
        }

        Model::getDb()->beginTransaction();

        try {
            foreach ($tasks as $task) {
                $this->revertAction($task);
                $task->revert();
                ++$count;
            }
        } catch (Exception $e) {
            Model::getDb()->rollBack();

            $this->cli->whisper(
                'Problem during rollback, rolling back the rollback :P'
            );

            throw new PDOException($e);
        }

        Model::getDb()->commit();

        $this->cli->info(
            "Finished rolling back $count task".
            (1 === $count ? '' : 's')
        );
    }

    /**
     * Reverts a message to it's previous state.
     */
    private function revertAction(TaskModel $task)
    {
        switch ($task->type) {
            case TaskModel::TYPE_READ:
            case TaskModel::TYPE_UNREAD:
                return (new MessageModel($task->message_id))
                    ->loadById()
                    ->setFlag(MessageModel::FLAG_SEEN, $task->old_value);

            case TaskModel::TYPE_FLAG:
            case TaskModel::TYPE_UNFLAG:
                return (new MessageModel($task->message_id))
                    ->loadById()
                    ->setFlag(MessageModel::FLAG_FLAGGED, $task->old_value);

            case TaskModel::TYPE_DELETE:
            case TaskModel::TYPE_UNDELETE:
                return (new MessageModel($task->message_id))
                    ->loadById()
                    ->setFlag(MessageModel::FLAG_DELETED, $task->old_value);

            case TaskModel::TYPE_COPY:
                // Mark as deleted any messages with this message-id
                // that are in the specified folder and that do not
                // have a unique ID field.
                return (new MessageModel($task->message_id))
                    ->loadById()
                    ->deleteCopiesFrom($task->folder_id);

            case TaskModel::TYPE_CREATE:
                // Mark the message and any corresponding outbox
                // message as deleted
                return (new MessageModel($task->message_id))
                    ->loadById()
                    ->deleteCreatedMessage();

            case TaskModel::TYPE_DELETE_OUTBOX:
                // Remove the deleted flag on the outbox message
                return (new OutboxModel($task->outbox_id))
                    ->loadById()
                    ->restore();

            case TaskModel::TYPE_SEND:
                // Restore the outbox message to a draft
                return (new OutboxModel($task->outbox_id))
                    ->loadById()
                    ->restore(true);
        }
    }
}
