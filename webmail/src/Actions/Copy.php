<?php

namespace App\Actions;

use Exception;
use App\Actions;
use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class Copy extends Base
{
    /**
     * Copies a message to the specified folder. This is a no-op if
     * there is already an un-deleted message with the same message
     * ID in the specified folder.
     *
     * @see Base for params
     *
     * @throws Exception
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        if (! isset($options[Actions::TO_FOLDER_ID])) {
            throw new Exception('Missing folder ID in copy action');
        }

        if (! is_numeric($options[Actions::TO_FOLDER_ID])) {
            throw new Exception('Invalid folder ID in copy action');
        }

        $copied = $message->copyTo($options[Actions::TO_FOLDER_ID]);

        if (is_a($copied, 'App\Model\Message')) {
            TaskModel::create(
                $message->id,
                $message->account_id,
                $this->getType(),
                null,
                $options[Actions::TO_FOLDER_ID]
            );
        }
    }

    public function getType()
    {
        return TaskModel::TYPE_COPY;
    }
}
