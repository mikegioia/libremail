<?php

namespace App\Sync\Actions;

use Pb\Imap\Mailbox;
use Zend\Mail\Storage;
use App\Model\Task as TaskModel;

class Read extends Base
{
    /**
     * Marks messages as seen.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
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
