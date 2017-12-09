<?php

namespace App\Actions;

use Exception;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;

abstract class Base
{
    /**
     * Iterates over the messages and calls subclass method.
     */
    public function run( array $messageIds )
    {
        if ( ! $messageIds
            || ! ( $messages = (new MessageModel)->getByIds( $messageIds ) ) )
        {
            return;
        }

        foreach ( $messages as $message ) {
            $this->update( $message );
        }
    }

    /**
     * Implemented by sub-classes.
     */
    abstract protected function getType();
    abstract protected function update( MessageModel $message );

    /**
     * Updates the flag for a message. Stores a row in the
     * tasks table, and both operations are wrapped in a SQL
     * transaction.
     * @param MessageModel $message
     * @param string $flag
     * @param bool $state
     * @throws Exception
     */
    protected function setFlag( MessageModel $message, $flag, $state )
    {
        $oldValue = $message->{$flag};
        $newValue = ( $state ) ? 1 : 0;

        // If the value has not changed, do nothing
        if ( (int) $oldValue === $newValue ) {
            return;
        }

        $taskModel = new TaskModel;
        $message->db()->beginTransaction();
        // We need to update this flag for all messsages with
        // the same message-id within the thread.
        $messageIds = $message->getSiblingIds();

        try {
            foreach ( $messageIds as $messageId ) {
                $taskModel->create(
                    $messageId,
                    $message->account_id,
                    $this->getType(),
                    $oldValue,
                    NULL );
                $message->setFlag( $messageId, $flag, $state );
            }
        }
        catch ( Exception $e ) {
            $message->db()->rollBack();
            throw $e;
        }

        $message->db()->commit();
    }
}