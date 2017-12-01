<?php

namespace App\Sync\Threads;

use App\Model\Message as MessageModel;

class Message
{
    private $id;
    private $threadId;
    private $messageId;
    // Indexed by message ID to be used as a set
    private $references = [];
    // Dirty flag, for updating database
    private $dirty = FALSE;

    public function __construct( MessageModel $message )
    {
        $this->threadId = NULL;
        $this->id = $message->id;

        // Store some message ID
        $messageId = trim( $message->message_id );
        $this->messageId = $messageId ?: $this->id;

        // Stores the initial set of message references
        $this->storeReferences( $message );
    }

    public function getId()
    {
        return $this->id;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

    public function getReferences()
    {
        return $this->references;
    }

    /**
     * Updates the internal set of references with any new
     * ones that are passed in. Returns stored set.
     * @param array $references
     * @return array
     */
    public function addReferences( $references )
    {
        $this->references += $references;

        return $this->references;
    }

    public function setThreadId( $threadId )
    {
        if ( $threadId && (int) $this->threadId !== (int) $threadId ) {
            $this->dirty = TRUE;
            $this->threadId = $threadId;
        }
    }

    public function save()
    {
        if ( ! $this->dirty ) {
            return TRUE;
        }

        (new MessageModel)->saveThreadId( $this->id, $this->threadId );
        $this->dirty = FALSE;
    }

    /**
     * Updates the initial set of references from the message object.
     * @param MessageModel $message
     */
    private function storeReferences( MessageModel $message )
    {
        $this->references = [];
        $this->references[] = $this->getMessageId();
        $replyTo = trim( $message->in_reply_to );

        if ( $replyTo ) {
            $this->references[] = $replyTo;
        }

        if ( $message->references ) {
            $references = explode( " ", $message->references );

            foreach ( $references as $reference ) {
                $referenceId = trim( $reference );

                if ( $referenceId ) {
                    $this->references[] = $referenceId;
                }
            }
        }

        $this->references = array_flip( array_unique( $this->references ) );
    }
}