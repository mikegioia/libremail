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
    public $recent;
    public $flagged;
    public $subject;
    public $deleted;
    public $answered;
    public $date_rfc;
    public $unique_id;
    public $folder_id;
    public $is_synced;
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
            'recent' => $this->recent,
            'flagged' => $this->flagged,
            'deleted' => $this->deleted,
            'subject' => $this->subject,
            'answered' => $this->answered,
            'date_rfc' => $this->date_rfc,
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'is_synced' => $this->is_synced,
            'text_html' => $this->text_html,
            'account_id' => $this->account_id,
            'message_id' => $this->account_id,
            'message_no' => $this->message_no,
            'text_plain' => $this->text_plain,
            'references' => $this->references,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'in_reply_to' => $this->in_reply_to
        ];
    }

    function getAccountId()
    {
        return (int) $this->account_id;
    }

    function getFolderId()
    {
        return (int) $this->folder_id;
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
                'is_synced =' => 1,
                'folder_id =' => $folderId,
                'account_id =' => $accountId,
            ], [
                'unique_id'
            ])->fetchAllObject();

        return $this->populate( $messages );
    }
}