<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Messages\MessageInterface;
use App\Model\Message as MessageModel;
use App\Model\Outbox as OutboxModel;
use App\Model\Task as TaskModel;
use App\Traits\ConfigTrait;

abstract class Base
{
    use ConfigTrait;

    public $messageClass = 'App\Model\Message';

    /**
     * Iterates over the messages and calls subclass method.
     * By default this returns MessageModel objects. However,
     * if the model class is defined, then that will be used.
     */
    public function run(array $messageIds, Folders $folders, array $options = [])
    {
        $model = new $this->messageClass;

        if (! $messageIds
            || ! ($messages = $model->getByIds($messageIds))
        ) {
            return;
        }

        foreach ($messages as $message) {
            $this->update($message, $folders, $options);
        }
    }

    /**
     * Implemented by sub-classes.
     */
    abstract public function getType();

    /**
     * @param MessageModel|OutboxModel $message SQL object to update
     * @param Folders $folders Meta info about mailboxes
     * @param array $options Any of the following:
     *     int `outbox_id`
     *  string `send_after`
     *   array `all_messages`
     *     int `to_folder_id`
     *     int `from_folder_id`
     *    bool `single_message`
     *  Outbox `outbox_message`
     */
    abstract public function update(
        MessageInterface $message,
        Folders $folders,
        array $options = []
    );

    /**
     * Updates the flag for a message. Stores a row in the
     * tasks table, and both operations are wrapped in a SQL
     * transaction.
     *
     * @param array $filters Optional query filters to limit siblings
     */
    protected function setFlag(
        MessageInterface $message,
        string $flag,
        bool $state,
        array $filters = [],
        array $options = [],
        bool $ignoreSiblings = false,
        string $type = null
    ) {
        $newValue = $state ? 1 : 0;
        $oldValue = $state ? 0 : 1;
        // We need to update this flag for all messsages with
        // the same message-id within the thread.
        $messages = true === $ignoreSiblings
            ? [$message]
            : $message->getSiblings($filters, $options);

        foreach ($messages as $sibling) {
            // Skip the message if it's the same
            if (intval($sibling->{$flag}) === $newValue) {
                continue;
            }

            $sibling->setFlag($flag, $state);

            TaskModel::create(
                $sibling->id,
                $sibling->account_id,
                $type ?: $this->getType(),
                $oldValue,
                null
            );
        }
    }

    /**
     * Restores a message from a folder to it's other deleted folders.
     * This happens from the Spam and Trash folders.
     */
    protected function restore(MessageModel $message, Folders $folders, array $options)
    {
        // Find the copy(ies) of this message and any other messages
        // in the thread, and that are in the trash folder.
        $messages = $message->getSiblings([
            'thread_id' => $message->thread_id,
            'folder_id' => $options[Actions::FROM_FOLDER_ID]
        ], [
            MessageModel::ALL_SIBLINGS => true,
            MessageModel::INCLUDE_DELETED => true
        ]);

        foreach ($messages as $trashed) {
            // Find the most recent message from any other folders and
            // restore that message.
            $messagesToRestore = $this->getUniqueDeletedSiblings(
                $trashed,
                $folders,
                $options
            );

            foreach ($messagesToRestore as $folderId => $restore) {
                // Restore the sibling message
                $this->setFlag(
                    $restore, MessageModel::FLAG_DELETED, false,
                    [], [], true, TaskModel::TYPE_DELETE
                );
                // Copy additional flags
                $this->setFlag(
                    $restore, MessageModel::FLAG_SEEN, 1 === (int) $trashed->seen,
                    [], [], true, TaskModel::TYPE_READ
                );
                $this->setFlag(
                    $restore, MessageModel::FLAG_FLAGGED, 1 === (int) $trashed->flagged,
                    [], [], true, TaskModel::TYPE_FLAG
                );
            }

            // Delete this message from the trash
            $this->setFlag(
                $trashed, MessageModel::FLAG_DELETED, true,
                [], [], true, TaskModel::TYPE_DELETE
            );
        }
    }

    private function getUniqueDeletedSiblings(
        MessageModel $message,
        Folders $folders,
        array $options
    ) {
        $unique = [];
        $siblings = $message->getSiblings([], [
            MessageModel::ONLY_DELETED => true,
            MessageModel::ALL_SIBLINGS => false
        ]);

        foreach ($siblings as $sibling) {
            $time = strtotime($sibling->date);

            if ((int) $sibling->folder_id === $options[Actions::FROM_FOLDER_ID]) {
                continue;
            }

            if (! isset($unique[$sibling->folder_id])
                || strtotime($unique[$sibling->folder_id]->date) > $time
            ) {
                $unique[$sibling->folder_id] = $sibling;
            }
        }

        return $unique;
    }
}
