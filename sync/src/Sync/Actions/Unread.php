<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

class Unread extends Base
{
    /**
     * Marks messages as un-seen (removes seen flag).
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
            [Storage::FLAG_SEEN],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNREAD;
    }
}
