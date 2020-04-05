<?php

namespace App\Sync\Actions;

use App\Model\Task as TaskModel;
use Laminas\Mail\Storage;
use Pb\Imap\Mailbox;

class Undelete extends Base
{
    /**
     * Marks messages as un-deleted (removes deleted flag).
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        if ($this->checkPurge()) {
            return;
        }

        return $mailbox->removeFlags(
            [$this->imapMessage->messageNum],
            [Storage::FLAG_DELETED],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNDELETE;
    }
}
