<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

abstract class Base
{
    /**
     * Iterates over the messages and calls subclass method.
     */
    public function run(array $messageIds, Folders $folders, array $options = [])
    {
        if (! $messageIds
            || ! ($messages = (new MessageModel)->getByIds($messageIds))
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

    abstract public function update(
        MessageModel $message,
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
        MessageModel $message,
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
                    [], [], true, TaskModel::TYPE_DELETE);
                // Copy additional flags
                $this->setFlag(
                    $restore, MessageModel::FLAG_SEEN, 1 == $trashed->seen,
                    [], [], true, TaskModel::TYPE_READ);
                $this->setFlag(
                    $restore, MessageModel::FLAG_FLAGGED, 1 == $trashed->flagged,
                    [], [], true, TaskModel::TYPE_FLAG);
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
