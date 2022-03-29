<?php

namespace App;

use App\Exceptions\ServerException;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use Exception;

class Rollback
{
    /**
     * Rolls all current logged tasks in the tasks table. Start
     * from the end and revert each one. This function sets an
     * HTTP 301 redirect header and exits.
     *
     * @throws ServerException
     */
    public function run(int $batchId)
    {
        $count = 0;
        $taskModel = new TaskModel();
        $tasks = $taskModel->getByBatchId($batchId);

        if (! $tasks) {
            Url::redirectBack();
        }

        Model::getDb()->beginTransaction();

        try {
            foreach ($tasks as $task) {
                if ($this->revertAction($task)) {
                    $task->revert();
                    ++$count;
                }
            }
        } catch (Exception $e) {
            Model::getDb()->rollBack();

            throw new ServerException('There was a problem undoing those tasks.', ERR_TASK_ROLLBACK);
        }

        Model::getDb()->commit();
        Session::alert('Action'.(1 === $count ? '' : 's').' undone.');
        Url::redirectBack();
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
                // have a unique ID field
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
                return (new OutboxModel())
                    ->loadById($task->outbox_id, true)
                    ->restore();

            case TaskModel::TYPE_SEND:
                // Restore the outbox message
                return (new OutboxModel())
                    ->loadById($task->outbox_id)
                    ->restore(true);
        }
    }
}
