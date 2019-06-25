<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

class Unflag extends Base
{
    /**
     * Marks messages as un-flagged (removes flagged flag).
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        return $mailbox->removeFlags(
            [$this->imapMessage->messageNum],
            [Storage::FLAG_FLAGGED],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
