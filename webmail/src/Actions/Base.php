<?php

namespace App\Actions;

use App\Model\MessageModel;

class Base
{
    /**
     * Iterates over the messages and calls subclass method.
     */
    public function run( array $messageIds )
    {
        if ( ! $messageIds
            || ! ( $messages = (new MessageModel)->getByIds() ) )
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
        $message->db()->beginTransaction();

        try {

        }
        catch ( \Exception $e ) {
            $message->db()->rollBack();
            throw $e;
        }

        $message->db()->commit();
    }
}