<?php

namespace App\Actions;

use App\Folders;
use App\MessageInterface;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class MarkUnread extends Base
{
    /**
     * Marks messages as unseen.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        $options = array_merge([
            MessageModel::ALL_SIBLINGS => true
        ], $options);

        $this->setFlag($message, MessageModel::FLAG_SEEN, false, [], $options);
    }

    public function getType()
    {
        return TaskModel::TYPE_UNREAD;
    }
}
