<?php

namespace App\Sync\Actions;

use App\Model\Task as TaskModel;
use Laminas\Mail\Storage;
use Pb\Imap\Mailbox;

class Read extends Base
{
    /**
     * Marks messages as seen.
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
            [Storage::FLAG_SEEN],
            $this->folder->name
        );
    }

    public function getType()
    {
        return TaskModel::TYPE_READ;
    }
}
