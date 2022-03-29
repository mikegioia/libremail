<?php

namespace App\Actions;

use App\Folders;
use App\Messages\MessageInterface;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use App\Url;

class ConvertDraft extends Delete
{
    /**
     * Converts an external draft to a local one. This marks the
     * external draft as deleted and creates a new local message
     * along with an outbox message.
     *
     * @see Base for params
     *
     * @throws Exception
     */
    public function update(MessageInterface $message, Folders $folders, array $options = [])
    {
        // Create both the outbox message the draft message
        $outbox = new OutboxModel($folders->getAccount());
        $outbox->copyMessageData($message, true)->save();

        $draft = (new MessageModel)->createOrUpdateDraft(
            $outbox,
            $folders->getDraftsId()
        );

        // Store the task for the rollback/undo to remove the created
        // draft and outbox messages
        TaskModel::create(
            $draft->id,
            $draft->account_id,
            TaskModel::TYPE_CREATE,
            null,
            null,
            $outbox->id
        );

        // Mark the external draft message as deleted
        parent::update($message, $folders, $options);

        // Set the URL redirect here; this is the only place where
        // we know all of the parameters
        Url::setRedirectUrl(Url::edit($outbox->id));
    }
}
