<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

class Copy extends Base
{
    /**
     * Copies a message to a new folder.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        // @TODO
        // new folder ID is on the task
    }

    public function getType()
    {
        return TaskModel::TYPE_COPY;
    }
}
