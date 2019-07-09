<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\MessageInterface;

class Restore extends Copy
{
    /**
     * Restores messages back to Inbox. This issues a copy command
     * to copy them to the inbox folder. It's not an un-delete.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        $options[Actions::TO_FOLDER_ID] = $folders->getInboxId();

        parent::update($message, $folders, $options);
    }
}
