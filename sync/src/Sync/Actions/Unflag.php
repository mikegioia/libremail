<?php

namespace App\Sync\Actions;

use App\Model\Task as TaskModel;
use Laminas\Mail\Storage;
use Pb\Imap\Mailbox;

class Unflag extends Base
{
    /**
     * Marks messages as un-flagged (removes flagged flag).
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
            [Storage::FLAG_FLAGGED],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_UNFLAG;
    }
}
