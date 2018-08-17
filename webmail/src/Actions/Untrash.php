<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Message as MessageModel;
use App\Actions\Delete as DeleteAction;

class Untrash extends DeleteAction
{
    /**
     * Removing from Trash is just deleting the trashed message. All other
     * copies remain in tact.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        if (! $folders->getTrashId()) {
            throw new ServerException('No Trash folder found', ERR_NO_TRASH_FOLDER);
        }

        $options[Actions::FROM_FOLDER_ID] = $folders->getTrashId();

        parent::update($message, $folders, $options);
    }
}
