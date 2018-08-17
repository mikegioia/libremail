<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Message as MessageModel;
use App\Actions\Delete as DeleteAction;

class Unspam extends DeleteAction
{
    /**
     * Removing from Spam is just deleting the spam message. All other
     * copies remain in tact.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        if (! $folders->getSpamId()) {
            throw new ServerException('No Spam folder found', ERR_NO_SPAM_FOLDER);
        }

        $options[Actions::FROM_FOLDER_ID] = $folders->getSpamId();

        parent::update($message, $folders, $options);
    }
}
