<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Model\Message as MessageModel;

class Archive extends Base
{
    /**
     * Marks deleted any messages in the Inbox folder.
     * @see Base for params
     */
    public function update( MessageModel $message, Folders $folders, array $options = [] )
    {
        $this->setFlag( $message, MessageModel::FLAG_DELETED, TRUE, [
            'folder_id' => $folders->getInboxId()
        ]);
    }

    public function getType()
    {
        return TaskModel::TYPE_DELETE;
    }
}
