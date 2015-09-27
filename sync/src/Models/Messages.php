<?php

namespace App\Models;

use Belt\Belt
  , Particle\Validator\Validator
  , App\Traits\Model as ModelTrait
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Messages extends \App\Model
{
    public $id;
    public $to;
    public $from;
    public $date;
    public $size;
    public $seen;
    public $draft;
    public $synced;
    public $recent;
    public $flagged;
    public $subject;
    public $deleted;
    public $answered;
    public $date_str;
    public $unique_id;
    public $folder_id;
    public $text_html;
    public $account_id;
    public $message_id;
    public $message_no;
    public $text_plain;
    public $references;
    public $is_deleted;
    public $created_at;
    public $in_reply_to;

    use ModelTrait;

    function getData()
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
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
            'in_reply_to' => $this->in_reply_to
        ];
    }

    function getFolderId()
    {
        return (int) $this->folder_id;
    }

    function getUniqueId()
    {
        return (int) $this->unique_id;
    }

    function getAccountId()
    {
        return (int) $this->account_id;
    }

    function isSynced()
    {
        return \Fn\intEq( $this->synced, 1 );
    }

    function isDeleted()
    {
        return \Fn\intEq( $this->deleted, 1 );
    }

    function getSyncedIdsByFolder( $accountId, $folderId )
    {
        if ( ! Belt::isNumber( $accountId )
            || ! Belt::isNumber( $folderId ) )
        {
            throw new ValidationException(
                "Account ID and Folder ID need to be integers." );
        }

        $messages = $this->db()->select(
            'messages', [
                'synced =' => 1,
                'folder_id =' => $folderId,
                'account_id =' => $accountId,
            ], [
                'unique_id'
            ])->fetchAllObject();

        return $this->populate( $messages );
    }

    /**
     * Create or updates a message record.
     * @param array $data
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    function save( $data = [] )
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

        // Date field is based off string date.
        if ( $this->date_str ) {
            $this->date_str = $this->cleanseDate( $this->date_str );
            $date = new \DateTime( $this->date_str );
            $this->date = $date->format( DATE_DATABASE );
        }

        $data = $this->getData();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "This message is missing required data."
                ));
        }

        // Check if this message exists
        $exists = $this->db()->select(
            'messages', [
                'folder_id' => $this->folder_id,
                'unique_id' => $this->unique_id,
                'account_id' => $this->account_id
            ])->fetchObject();

        exit( 'Save method not implemented yet' );

        if ( $exists ) {
            $this->id = $exists->id;
            $updated = $this->db()->update(
                'folders', [
                    'is_deleted' => 0
                ], [
                    'name' => $this->name,
                    'account_id' => $this->account_id
                ]);

            if ( ! $updated ) {
                throw new DatabaseUpdateException( MESSAGE );
            }

            return;
        }

        $createdAt = new \DateTime;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $newMessage = $this->db()->insert( 'messages', $data );

        if ( ! $newMessage ) {
            throw new DatabaseInsertException( MESSAGE );
        }

        $this->setData( $newMessage );
    }

    /**
     * Saves the meta information for a message as data
     * on the class object. We can't assume any fields
     * will exist on the record.
     * @param array $meta
     */
    function setMailMeta( $meta )
    {
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
            'message_id' => \Fn\get( $meta, 'message_id' )
        ]);
    }

    /**
     * Saves the full data from an IMAP message to the
     * message object.
     * @param array $mail
     */
    function setMailData( $mail )
    {

    }

    private function cleanseDate( $dateStr )
    {
        $parenPosition = strpos( $dateStr, "(" );

        if ( $parenPosition !== FALSE ) {
            $dateStr = substr( $dateStr, 0, $parenPosition );
        }

        return $dateStr;
    }
}