<?php

namespace App\Sync\Actions;

use App\Exceptions\NotFound as NotFoundException;
use App\Model\Folder as FolderModel;
use App\Model\Task as TaskModel;
use Pb\Imap\Mailbox;

class Copy extends Base
{
    /**
     * Copies a message to a new folder.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        $toFolder = (new FolderModel)->getById($this->task->folder_id);

        if (! $toFolder || ! $toFolder->name) {
            throw new NotFoundException(FOLDER, $this->task->folder_id);
        }

        $mailbox->copy(
            $toFolder->name,
            $this->imapMessage->messageNum
        );

        $this->checkPurge();
    }

    public function getType()
    {
        return TaskModel::TYPE_COPY;
    }
}
