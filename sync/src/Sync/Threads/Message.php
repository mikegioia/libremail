<?php

namespace App\Sync\Threads;

use App\Model\Message as MessageModel;

class Message
{
    // Storage of all SQL IDs with this message ID
    private $ids = [];
    // Lowest SQL ID of all messages in the thread
    private $threadId;
    // Message ID header from the IMAP message
    private $messageId;
    // Indexed by message ID to be used as a set
    private $references = [];
    // Dirty flag, for updating database
    private $dirty = FALSE;

    public function __construct( MessageModel $message )
    {
        $this->threadId = NULL;
        $this->ids[] = $message->id;
        $this->messageId = trim( $message->message_id );

        // If there's no message ID, then we need to give it one.
        // Even if it's a random ID we still need it in case this
        // message connects other messages through its references.
        if ( ! strlen( $this->messageId ) ) {
            $this->messageId = "message-{$message->id}";
        }

        // Stores the initial set of message references
        $this->storeReferences( $message );
    }

    public function getIds()
    {
        return $this->ids;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

    public function getReferences()
    {
        return $this->references;
    }

    public function getThreadId()
    {
        return ( $this->threadId )
            ? $this->threadId
            : min( $this->ids );
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

        $messageModel = new MessageModel;

        foreach ( $this->ids as $id ) {
            $messageModel->saveThreadId( (int) $id, (int) $this->threadId );
        }

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

    /**
     * Merge another Message object into this one.
     * @param Message $message
     */
    public function merge( Message $message )
    {
        $this->ids = array_unique(
            array_merge( $this->ids, $message->getIds() ) );
        $this->references = $this->references + $message->getReferences();
    }
}