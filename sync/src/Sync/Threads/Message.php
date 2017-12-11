<?php

namespace App\Sync\Threads;

use App\Model\Message as MessageModel;

class Message
{
    // Storage of all SQL IDs with this message ID
    public $ids = [];
    // Lowest SQL ID of all messages in the thread
    public $threadId;
    // Message ID header from the IMAP message
    public $messageId;
    // Unix time for the message
    public $timestamp;
    // Unique hash of the simplified subject line
    public $subjectHash;
    // Indexed by message ID to be used as a set
    public $references = [];
    // Dirty flag, for updating database
    private $dirty = FALSE;

    public function __construct( MessageModel $message )
    {
        $this->threadId = $message->thread_id;
        $this->timestamp = strtotime( $message->date );
        $this->messageId = trim( $message->message_id );
        $this->subjectHash = $message->getSubjectHash();

        if ( $message->id ) {
            $this->ids[] = $message->id;
        }

        // If there's no message ID, then we need to give it one.
        // Even if it's a random ID we still need it in case this
        // message connects other messages through its references.
        if ( ! strlen( $this->messageId ) ) {
            $this->messageId = "message-{$message->id}";
        }

        // Stores the initial set of message references
        $this->storeReferences( $message );
    }

    public function getThreadId()
    {
        return ( $this->threadId )
            ? $this->threadId
            : min( $this->ids ?: [ 0 ] );
    }

    public function hasUpdate()
    {
        return $this->dirty && $this->ids;
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

    /**
     * Save the thread ID for any internal IDs.
     * @param MessageModel $model
     */
    public function save( MessageModel $model )
    {
        if ( ! $this->dirty ) {
            return;
        }

        $this->dirty = FALSE;

        if ( $this->ids ) {
            $model->saveThreadId( $this->ids, $this->threadId );
        }
    }

    /**
     * Updates the initial set of references from the message object.
     * @param MessageModel $message
     */
    private function storeReferences( MessageModel $message )
    {
        $this->references = [];
        $this->references[] = $this->messageId;
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
            array_merge( $this->ids, $message->ids ) );
        $this->references = $this->references + $message->references;

        // If any message in this thread is missing a thread ID, then
        // we want to update the whole collection
        if ( ! $message->threadId ) {
            $this->dirty = TRUE;
        }
    }
}