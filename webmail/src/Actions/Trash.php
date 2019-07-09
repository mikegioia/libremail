<?php

namespace App\Actions;

use App\Thread;
use App\Actions;
use App\Folders;
use App\MessageInterface;
use App\Exceptions\ServerException;
use App\Actions\Copy as CopyAction;

class Trash extends Delete
{
    /**
     * Copies a message to the Trash folder.
     *
     * @see Base for params
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        if (! $folders->getTrashId()) {
            throw new ServerException('No Trash folder found', ERR_NO_TRASH_FOLDER);
        }

        $thread = Thread::constructFromMessage($message, $folders);
        $options = array_merge([
            Actions::TO_FOLDER_ID => $folders->getTrashId()
        ], $options);

        // For this operation, we're going to perform the actions
        // on every message in the thread unless the option to only
        // remove this one message is set.
        if (true === $options[Actions::SINGLE_MESSAGE] ?? false) {
            $messages = [$message];
        } else {
            $messages = $thread->getMessages();
        }

        foreach ($messages as $threadMessage) {
            // Copy to trash
            (new CopyAction)->update($threadMessage, $folders, $options);

            // Mark it as deleted. Setting the from-folder-id option
            // will restrict our deletions to all folders that the message
            // belongs to except the trash folder.
            $options[Actions::FROM_FOLDER_ID] = array_diff(
                $thread->getFolderIds(),
                [$folders->getTrashId()]);

            if (count($options[Actions::FROM_FOLDER_ID]) > 0) {
                parent::update($threadMessage, $folders, $options);
            }
        }
    }
}
