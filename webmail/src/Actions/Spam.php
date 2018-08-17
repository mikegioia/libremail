<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Exceptions\ServerException;
use App\Model\Message as MessageModel;

class Spam extends Copy
{
    /**
     * Copies a message to the Spam folder.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        if (! $folders->getSpamId()) {
            throw new ServerException('No Spam folder found', ERR_NO_SPAM_FOLDER);
        }

        $options[Actions::TO_FOLDER_ID] = $folders->getSpamId();
        parent::update($message, $folders, $options);
    }
}
