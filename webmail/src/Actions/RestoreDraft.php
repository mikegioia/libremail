<?php

namespace App\Actions;

use App\Folders;
use App\Messages\MessageInterface;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use App\Url;

class RestoreDraft extends Base
{
    public $messageClass = 'App\Model\Outbox';

    /**
     * Copies a message to the Spam folder.
     *
     * @param MessageInterface $message OutboxModel object
     *
     * @see Base for params
     */
    public function update(MessageInterface $outbox, Folders $folders, array $options = [])
    {
        // Mark the draft as deleted
        $outbox->softDelete();

        // Store the task to un-delete this outbox message
        TaskModel::create(
            0,
            $outbox->account_id,
            TaskModel::TYPE_DELETE_OUTBOX,
            (int) $outbox->deleted,
            null,
            $outbox->id
        );

        // Create a new clone of this message with fields cleared
        $outbox->reset()->save();

        // Create a new draft message with this message's data
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

        // Set the URL redirect here; this is the only place where
        // we know all of the parameters
        Url::setRedirectUrl(Url::edit($outbox->id));
    }

    public function getType()
    {
        return TaskModel::TYPE_DELETE_OUTBOX;
    }
}
