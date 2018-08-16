<?php

namespace App;

use App\Model;
use PDOException;
use App\Model\Task as TaskModel;
use App\Exceptions\ServerException;
use App\Model\Message as MessageModel;

class Rollback
{
    /**
     * Rolls all current logged tasks in the tasks table. Start
     * from the end and revert each one.
     *
     * @throws ServerException
     *
     * @return HTTP 303 redirect
     */
    public function run(int $batchId)
    {
        $count = 0;
        $taskModel = new TaskModel;
        $messageModel = new MessageModel;
        $tasks = $taskModel->getByBatchId($batchId);

        if (! $tasks) {
            Url::redirectBack();
        }

        Model::getDb()->beginTransaction();

        try {
            foreach ($tasks as $task) {
                if ($this->revertAction($task, $messageModel)) {
                    $task->revert();
                    ++$count;
                }
            }
        } catch (Exception $e) {
            Model::getDb()->rollBack();

            throw new ServerException(
                'There was a problem undoing those tasks.',
                ERR_TASK_ROLLBACK);
        }

        Model::getDb()->commit();
        Session::notify('Action'.(1 === $count ? '' : 's').' undone.');
        Url::redirectBack();
    }

    /**
     * Reverts a message to it's previous state.
     */
    private function revertAction(TaskModel $task, MessageModel $message)
    {
        switch ($task->type) {
            case TaskModel::TYPE_READ:
            case TaskModel::TYPE_UNREAD:
                return $message->setFlag(
                    MessageModel::FLAG_SEEN,
                    $task->old_value,
                    $task->message_id);

            case TaskModel::TYPE_FLAG:
            case TaskModel::TYPE_UNFLAG:
                return $message->setFlag(
                    MessageModel::FLAG_FLAGGED,
                    $task->old_value,
                    $task->message_id);

            case TaskModel::TYPE_DELETE:
            case TaskModel::TYPE_UNDELETE:
                return $message->setFlag(
                    MessageModel::FLAG_DELETED,
                    $task->old_value,
                    $task->message_id);

            case TaskModel::TYPE_COPY:
                // Mark as deleted any messages with this message-id
                // that are in the specified folder and that do not
                // have a unique ID field.
                return $message->deleteCopiedMessages(
                    $task->message_id,
                    $task->folder_id);
        }
    }
}
