<?php

namespace App\Actions;

use App\Actions;
use App\Actions\Copy as CopyAction;
use App\Exceptions\ServerException;
use App\Folders;
use App\Messages\MessageInterface;
use App\Thread;

class Spam extends Delete
{
    /**
     * Copies a message to the Spam folder.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        if (! $folders->getSpamId()) {
            throw new ServerException('No Spam folder found', ERR_NO_SPAM_FOLDER);
        }

        $thread = Thread::constructFromMessage($message, $folders);
        $options = array_merge([
            Actions::TO_FOLDER_ID => $folders->getSpamId()
        ], $options);

        // For this operation, we're going to perform the actions
        // on every message in the thread unless the option to only
        // remove this one message is set.
        if (true === ($options[Actions::SINGLE_MESSAGE] ?? false)) {
            $messages = [$message];
        } else {
            $messages = $thread->getMessages();
        }

        foreach ($messages as $threadMessage) {
            // Copy to spam
            (new CopyAction)->update($threadMessage, $folders, $options);

            // Mark it as deleted. Setting the from-folder-id option
            // will restrict our deletions to all folders that the message
            // belongs to except the spam folder.
            $options[Actions::FROM_FOLDER_ID] = array_diff(
                $thread->getFolderIds(),
                [$folders->getSpamId()]);

            if (count($options[Actions::FROM_FOLDER_ID]) > 0) {
                parent::update($threadMessage, $folders, $options);
            }
        }
    }
}
