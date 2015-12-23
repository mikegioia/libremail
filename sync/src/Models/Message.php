<?php

namespace App\Models;

use Belt\Belt
  , Particle\Validator\Validator
  , Pb\Imap\Message as ImapMessage
  , App\Traits\Model as ModelTrait
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\DatabaseUpdate as DatabaseUpdateException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Message extends \App\Model
{
    public $id;
    public $to;
    public $cc;
    public $from;
    public $date;
    public $size;
    public $seen;
    public $draft;
    public $synced;
    public $recent;
    public $flagged;
    public $deleted;
    public $subject;
    public $answered;
    public $reply_to;
    public $date_str;
    public $unique_id;
    public $folder_id;
    public $text_html;
    public $account_id;
    public $message_id;
    public $message_no;
    public $text_plain;
    public $references;
    public $created_at;
    public $in_reply_to;
    public $attachments;

    private $unserializedAttachments;

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'cc' => $this->cc,
            'from' => $this->from,
            'date' => $this->date,
            'size' => $this->size,
            'seen' => $this->seen,
            'draft' => $this->draft,
            'synced' => $this->synced,
            'recent' => $this->recent,
            'flagged' => $this->flagged,
            'deleted' => $this->deleted,
            'subject' => $this->subject,
            'answered' => $this->answered,
            'reply_to' => $this->reply_to,
            'date_str' => $this->date_str,
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'text_html' => $this->text_html,
            'account_id' => $this->account_id,
            'message_id' => $this->account_id,
            'message_no' => $this->message_no,
            'text_plain' => $this->text_plain,
            'references' => $this->references,
            'created_at' => $this->created_at,
            'in_reply_to' => $this->in_reply_to,
            'attachments' => $this->attachments
        ];
    }

    public function getFolderId()
    {
        return (int) $this->folder_id;
    }

    public function getUniqueId()
    {
        return (int) $this->unique_id;
    }

    public function getAccountId()
    {
        return (int) $this->account_id;
    }

    public function isSynced()
    {
        return \Fn\intEq( $this->synced, 1 );
    }

    public function isDeleted()
    {
        return \Fn\intEq( $this->deleted, 1 );
    }

    public function getAttachments()
    {
        if ( ! is_null( $this->unserializedAttachments ) ) {
            return $this->unserializedAttachments;
        }

        $this->unserializedAttachments = @unserialize( $this->attachments );
        return $this->unserializedAttachments;
    }

    /**
     * Returns a list of integer unique_ids given an account ID
     * and a folder ID to search.
     * @param int $accountId
     * @param int $folderId
     * @return array
     */
    public function getSyncedIdsByFolder( $accountId, $folderId )
    {
        $ids = [];
        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $messages = $this->db()
            ->select([ 'unique_id' ])
            ->from( 'messages' )
            ->where( 'synced', '=', 1 )
            ->where( 'deleted', '=', 0 )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetchAll();

        if ( ! $messages ) {
            return $ids;
        }

        foreach ( $messages as $message ) {
            $ids[] = $message[ 'unique_id' ];
        }

        return $ids;
    }

    /**
     * Create or updates a message record.
     * @param array $data
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save( $data = [] )
    {
        $val = new Validator;
        $val->required( 'folder_id', 'Folder ID' )->integer();
        $val->required( 'unique_id', 'Unique ID' )->integer();
        $val->required( 'account_id', 'Account ID' )->integer();
        // Optional fields
        $val->required( 'size', 'Size' )->integer();
        $val->required( 'message_no', 'Message Number' )->integer();
        $val->optional( 'date', 'Date' )->datetime( DATE_DATABASE );
        $val->optional( 'subject', 'Subject' )->lengthBetween( 0, 250 );
        $val->optional( 'date_str', 'RFC Date' )->lengthBetween( 0, 100 );
        $val->optional( 'seen', 'Seen' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'message_id', 'Message ID' )->lengthBetween( 0, 250 );
        $val->optional( 'draft', 'Draft' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'in_reply_to', 'In-Reply-To' )->lengthBetween( 0, 250 );
        $val->optional( 'recent', 'Recent' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'flagged', 'Flagged' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'deleted', 'Deleted' )->callback([ $this, 'isValidFlag' ]);
        $val->optional( 'answered', 'Answered' )->callback([ $this, 'isValidFlag' ]);
        $this->setData( $data );
        $data = $this->getData();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "This message is missing required data."
                ));
        }

        // Check if this message exists
        $exists = $this->db()
            ->select()
            ->from( 'messages' )
            ->where( 'folder_id', '=', $this->folder_id )
            ->where( 'unique_id', '=', $this->unique_id )
            ->where( 'account_id', '=', $this->account_id )
            ->execute()
            ->fetchObject();

        if ( $exists ) {
            $this->id = $exists->id;
            unset( $data[ 'id' ] );
            unset( $data[ 'created_at' ] );
            $updated = $this->db()
                ->update( $data )
                ->table( 'messages' )
                ->where( 'id', '=', $this->id )
                ->execute();

            if ( $updated === FALSE ) {
                throw new DatabaseUpdateException(
                    MESSAGE,
                    $this->db()->getError() );
            }

            return;
        }

        $createdAt = new \DateTime;
        unset( $data[ 'id' ] );
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $newMessageId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'messages' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newMessageId ) {
            throw new DatabaseInsertException(
                MESSAGE,
                $this->getError() );
        }

        $this->id = $newMessageId;
    }

    /**
     * Saves the meta information and content for a message as data
     * on the class object.
     * @param array $meta
     */
    public function setMessageData( ImapMessage $message )
    {
        print_r($message);exit;

        /*
        $this->setData([
            'to' => \Fn\get( $meta, 'to' ),
            'from' => \Fn\get( $meta, 'from' ),
            'size' => \Fn\get( $meta, 'size' ),
            'seen' => \Fn\get( $meta, 'seen' ),
            'draft' => \Fn\get( $meta, 'draft' ),
            'date_str' => \Fn\get( $meta, 'date' ),
            'unique_id' => \Fn\get( $meta, 'uid' ),
            'recent' => \Fn\get( $meta, 'recent' ),
            'flagged' => \Fn\get( $meta, 'flagged' ),
            'deleted' => \Fn\get( $meta, 'deleted' ),
            'subject' => \Fn\get( $meta, 'subject' ),
            'message_no' => \Fn\get( $meta, 'msgno' ),
            'answered' => \Fn\get( $meta, 'answered' ),
            'message_id' => \Fn\get( $meta, 'message_id' ),
        // cc and replyTo fields come in as arrays with the address
        // as the index and the name as the value. Create the proper
        // comma separated strings for these fields.
        $cc = \Fn\get( $message, 'cc', [] );
        $replyTo = \Fn\get( $message, 'replyTo', [] );

        $this->setData([
            'date' => $message->date,
            'text_html' => $message->textHtml,
            'text_plain' => $message->textPlain,
            'cc' => $this->formatAddress( $cc ),
            'reply_to' => $this->formatAddress( $replyTo ),
            'attachments' => $this->formatAttachments( $message->getAttachments() )
        ]);
        */
    }

    /**
     * Takes in an array of message unique IDs and marks them all as
     * deleted in the database.
     * @param array $uniqueIds
     */
    public function markDeleted( $uniqueIds, $accountId, $folderId )
    {
        if ( ! is_array( $uniqueIds ) || ! count( $uniqueIds ) ) {
            return;
        }

        $this->requireInt( $folderId, "Folder ID" );
        $this->requireInt( $accountId, "Account ID" );
        $updated = $this->db()
            ->update([ 'deleted' => 1 ])
            ->table( 'messages' )
            ->where( 'folder_id', '=', $folderId )
            ->where( 'account_id', '=', $accountId )
            ->whereIn( 'unique_id', $uniqueIds )
            ->execute();

        if ( ! $updated ) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError() );
        }
    }

    /**
     * Takes in an array of addresses and formats them in a list.
     * @return string
     */
    private function formatAddress( $addresses )
    {
        if ( ! is_array( $addresses ) ) {
            return NULL;
        }

        $formatted = [];

        foreach ( $addresses as $email => $name ) {
            $formatted[] = "$name <$email>";
        }

        return implode( ", ", $formatted );
    }

    /**
     * Attachments need to be serialized. They come in as an array
     * of objects with name, path, and id fields.
     * @param Attachment array $attachments
     * @return string
     */
    private function formatAttachments( $attachments )
    {
        if ( ! is_array( $attachments ) ) {
            return NULL;
        }

        $formatted = [];

        foreach ( $attachments as $attachment ) {
            $formatted[] = (array) $attachment;
        }

        return @serialize( $formatted );
    }
}