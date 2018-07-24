<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class MarkRead extends Base
{
    /**
     * Marks messages as seen.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $this->setFlag($message, MessageModel::FLAG_SEEN, true, [], [
            MessageModel::ALL_SIBLINGS => true
        ]);
    }

    public function getType()
    {
        return TaskModel::TYPE_READ;
    }
}
