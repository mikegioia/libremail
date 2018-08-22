<?php

namespace App\Actions;

use App\Thread;
use App\Actions;
use App\Folders;
use App\Exceptions\ServerException;
use App\Actions\Copy as CopyAction;
use App\Model\Message as MessageModel;

class Spam extends Delete
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

        $options = array_merge([
            Actions::TO_FOLDER_ID => $folders->getSpamId()
        ], $options);

        // For this operation, we're going to perform the actions
        // on every message in the thread.
        $thread = Thread::constructFromMessage($message, $folders);

        foreach ($thread->getMessages() as $threadMessage) {
            // Copy to spam
            (new CopyAction)->update($threadMessage, $folders, $options);

            // Mark it as deleted. Setting the from-folder-id option
            // will restrict our deletions to all folders that the message
            // belongs to except the spam folder.
            $options[Actions::FROM_FOLDER_ID] = array_diff(
                $thread->getFolderIds(),
                [$folders->getSpamId()]);

            parent::update($threadMessage, $folders, $options);
        }
    }
}
