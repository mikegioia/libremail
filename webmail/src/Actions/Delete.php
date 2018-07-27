<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class Delete extends Base
{
    /**
     * Deletes messages, optionally only from the specified folder ID.
     * If no folder ID is present, all messages with the message-id
     * that are in the same thread will be deleted.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $filters = [];

        if (isset($options[Actions::FROM_FOLDER_ID])) {
            $filters['folder_id'] = $options[Actions::FROM_FOLDER_ID];
        }

        $this->setFlag($message, MessageModel::FLAG_DELETED, true, $filters, $options);
    }

    public function getType()
    {
        return TaskModel::TYPE_DELETE;
    }
}
