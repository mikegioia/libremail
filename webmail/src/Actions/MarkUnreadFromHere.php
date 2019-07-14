<?php

namespace App\Actions;

use App\Folders;
use App\Messages\MessageInterface;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class MarkUnreadFromHere extends Base
{
    /**
     * Marks the selected message and all future messages
     * in the thread as seen.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        $options = array_merge([
            MessageModel::ALL_SIBLINGS => true,
            MessageModel::ONLY_FUTURE_SIBLINGS => true
        ], $options);

        $this->setFlag($message, MessageModel::FLAG_SEEN, false, [], $options);
    }

    public function getType()
    {
        return TaskModel::TYPE_UNREAD;
    }
}
