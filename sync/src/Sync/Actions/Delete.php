<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

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
