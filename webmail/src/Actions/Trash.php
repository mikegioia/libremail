<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Message as MessageModel;

class Trash extends Copy
{
    /**
     * Copies a message to the Trash folder.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $options[Actions::TO_FOLDER_ID] = $folders->getTrashId();
        parent::update($message, $folders, $options);
    }
}
