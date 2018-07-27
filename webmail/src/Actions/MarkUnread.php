<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class MarkUnread extends Base
{
    /**
     * Marks messages as unseen.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $option = [])
    {
        $this->setFlag($message, MessageModel::FLAG_SEEN, false, [], $options);
    }

    public function getType()
    {
        return TaskModel::TYPE_UNREAD;
    }
}
