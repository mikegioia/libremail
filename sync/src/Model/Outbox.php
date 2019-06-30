<?php

namespace App\Model;

use App\Model;
use App\Traits\Model as ModelTrait;

class Outbox extends Model
{
    public $id;
    public $to;
    public $cc;
    public $bcc;
    public $from;
    public $sent;
    public $draft;
    public $locked;
    public $deleted;
    public $subject;
    public $reply_to;
    public $attempts;
    public $parent_id;
    public $text_html;
    public $account_id;
    public $text_plain;
    public $created_at;
    public $updated_at;
    public $update_history;

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'from' => $this->from,
            'sent' => $this->sent,
            'draft' => $this->draft,
            'locked' => $this->locked,
            'deleted' => $this->deleted,
            'subject' => $this->subject,
            'reply_to' => $this->reply_to,
            'attempts' => $this->attempts,
            'parent_id' => $this->parent_id,
            'text_html' => $this->text_html,
            'account_id' => $this->account_id,
            'text_plain' => $this->text_plain,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'update_history' => $this->update_history
        ];
    }

    /**
     * @throws ValidationException
     *
     * @return bool
     */
    public function softDelete()
    {
        $this->requireInt($this->id, 'Outbox ID');

        $deleted = $this->db()
            ->update(['deleted' => 1])
            ->table('outbox')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($deleted) ? $deleted : false;
    }
}
