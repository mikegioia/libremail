<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;
use App\Actions\Delete as DeleteAction;

class Unflag extends DeleteAction
{
    /**
     * Marks messages as unflagged. We need to remove this message
     * from the starred folder if it exists.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $this->setFlag($message, MessageModel::FLAG_FLAGGED, false);

        (new DeleteAction)->update($message, $folders, $options);
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
