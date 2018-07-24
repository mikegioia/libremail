<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\MessageModel;
use App\Actions\Copy as CopyAction;

class Restore extends CopyAction
{
    /**
     * Restores messages back to inbox. This issues a copy command
     * to copy them to the inbox folder. It's not an un-delete.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $options[Actions::TO_FOLDER_ID] = $folders->getInboxId();
        parent::update($message, $folders, $options);
    }
}
