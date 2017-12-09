<?php

namespace App\Actions;

use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Model\Message as MessageModel;

class Unflag extends Base
{
    public function update( MessageModel $message )
    {
        $this->setFlag( $message, MessageModel::FLAG_FLAGGED, FALSE );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
