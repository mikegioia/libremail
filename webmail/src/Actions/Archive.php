<?php

namespace App\Actions;

use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Model\Message as MessageModel;

class Archive extends Base
{
    public function update( MessageModel $message )
    {
        //
    }

    public function getType()
    {
        return TaskModel::TYPE_ARCHIVE;
    }
}
