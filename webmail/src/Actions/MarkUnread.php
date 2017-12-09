<?php

namespace App\Actions;

use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Model\Message as MessageModel;

class MarkUnread extends Base
{
    public function update( MessageModel $message )
    {
        $this->setFlag( $message, MessageModel::FLAG_SEEN, FALSE );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNREAD;
    }
}
