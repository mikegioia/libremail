<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

class RestoreDraft extends Base
{
    public function run(array $messageIds, Folders $folders, array $options = [])
    {
        // Load outbox messages
        exit('in here');
    }
}
