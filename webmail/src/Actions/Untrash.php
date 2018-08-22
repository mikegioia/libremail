<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Exceptions\ServerException;
use App\Model\Message as MessageModel;

class Untrash extends Delete
{
    /**
     * Removing from Trash requires deleting the trashed message and
     * copying the message back to any folders it was removed from.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        if (! $folders->getTrashId()) {
            throw new ServerException('No Trash folder found', ERR_NO_TRASH_FOLDER);
        }

        $options[Actions::FROM_FOLDER_ID] = $folders->getTrashId();

        $this->restore($message, $folders, $options);
    }
}
