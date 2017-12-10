<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Model\Message as MessageModel;

class Unflag extends Base
{
    /**
     * Marks messages as unflagged.
     * @see Base for params
     */
    public function update( MessageModel $message, Folders $folders, array $options = [] )
    {
        $this->setFlag( $message, MessageModel::FLAG_FLAGGED, FALSE );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
