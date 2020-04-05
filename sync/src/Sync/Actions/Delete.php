<?php

namespace App\Sync\Actions;

use App\Model\Task as TaskModel;
use Laminas\Mail\Storage;
use Pb\Imap\Mailbox;

class Delete extends Base
{
    /**
     * Marks messages as deleted.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        if ($this->checkPurge()) {
            return;
        }

        return $mailbox->addFlags(
            [$this->imapMessage->messageNum],
            [Storage::FLAG_DELETED],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_DELETE;
    }
}
