<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

class Flag extends Base
{
    /**
     * Marks messages as flagged.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        return $mailbox->addFlags(
            [$this->imapMessage->messageNum],
            [Storage::FLAG_FLAGGED],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_FLAG;
    }
}
