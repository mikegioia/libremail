<?php

namespace App\Actions;

use App\Actions;
use App\Actions\Delete as DeleteAction;
use App\Folders;
use App\Messages\MessageInterface;
use App\Model\Message as MessageModel;
use App\Model\Task as TaskModel;

class Unflag extends DeleteAction
{
    /**
     * Marks messages as unflagged. We need to remove this message
     * from the starred folder if it exists.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        $this->setFlag($message, MessageModel::FLAG_FLAGGED, false, [], $options);

        // If there's a starred folder, remove the message from it
        if ($folders->getStarredId()) {
            $options = array_merge($options, [
                Actions::FROM_FOLDER_ID => $folders->getStarredId()
            ]);

            parent::update($message, $folders, $options);
        }
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
