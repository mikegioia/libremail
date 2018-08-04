<?php

namespace App\Actions;

use App\Folders;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

abstract class Base
{
    /**
     * Iterates over the messages and calls subclass method.
     *
     * @param array $messageIds
     * @param Folders $folders
     * @param array $options
     */
    public function run(array $messageIds, Folders $folders, array $options = [])
    {
        if (! $messageIds
            || ! ($messages = (new MessageModel)->getByIds($messageIds)))
        {
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

    abstract public function update(MessageModel $message, Folders $folders, array $options = []);

    /**
     * Updates the flag for a message. Stores a row in the
     * tasks table, and both operations are wrapped in a SQL
     * transaction.
     *
     * @param MessageModel $message
     * @param string $flag
     * @param bool $state
     * @param array $filters Optional filters to limit siblings
     * @param array $options
     */
    protected function setFlag(
        MessageModel $message,
        string $flag,
        bool $state,
        array $filters = [],
        array $options = [])
    {
        $taskModel = new TaskModel;
        $newValue = $state ? 1 : 0;
        $oldValue = $state ? 0 : 1;
        // We need to update this flag for all messsages with
        // the same message-id within the thread.
        $messages = $message->getSiblings($filters, $options);

        foreach ($messages as $sibling) {
            // Skip the message if it's the same
            if ($sibling->{$flag} == $newValue) {
                continue;
            }

            $sibling->setFlag($flag, $state);
            $taskModel->create(
                $sibling->id,
                $sibling->account_id,
                $this->getType(),
                $oldValue,
                null);
        }
    }
}
