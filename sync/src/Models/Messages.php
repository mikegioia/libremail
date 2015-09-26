<?php

namespace App\Models;

use Particle\Validator\Validator
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
    public $unique_id;
    public $folder_id;
    public $account_id;
    public $message_id; // Max 250 characters
    public $message_no;
    public $is_deleted;
    public $created_at;
    public $in_reply_to;

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
            'unique_id' => $this->unique_id,
            'folder_id' => $this->folder_id,
            'account_id' => $this->account_id,
            'message_id' => $this->account_id,
            'message_no' => $this->message_no,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'in_reply_to' => $this->in_reply_to
        ];
    }

    function getIdsByFolder( $accountId, $folderId )
    {
        $messages = $this->db()->select(
            'messages', [
                'folder_id = ' => $folderId,
                'account_id =' => $accountId,
            ], [
                'unique_id'
            ])->fetchAllObject();

        return $this->populate( $accounts );
    }
}