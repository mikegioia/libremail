<?php

namespace App\Model;

use PDO;
use App\Model;
use App\Traits\Model as ModelTrait;
use App\Exceptions\NotFound as NotFoundException;

class Outbox extends Model
{
    use ModelTrait;

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

    const DELETED = 'deleted';
    const RESTORED = 'restored';

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
     * @throws NotFoundException
     */
    public function loadById()
    {
        if (! $this->id) {
            throw new NotFoundException(OUTBOX);
        }

        $outbox = $this->getById($this->id);

        if ($outbox) {
            $this->setData($outbox);
        } else {
            throw new NotFoundException(OUTBOX);
        }

        return $this;
    }

    public function getById(int $id)
    {
        if ($id <= 0) {
            return;
        }

        return $this->db()
            ->select()
            ->from('outbox')
            ->where('id', '=', $id)
            ->execute()
            ->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Removes the delete flag.
     *
     * @throws ValidationException
     */
    public function restore(bool $draft = false)
    {
        $this->requireInt($this->id, 'Outbox ID');
        $this->updateHistory(self::RESTORED);

        $this->deleted = 0;

        $data = [
            'deleted' => 0,
            'update_history' => $this->update_history
        ];

        if (true === $draft) {
            $this->draft = 1;
            $data['draft'] = 1;
        }

        $restored = $this->db()
            ->update($data)
            ->table('outbox')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($restored) ? $restored : false;
    }

    /**
     * @throws ValidationException
     *
     * @return bool
     */
    public function softDelete()
    {
        $this->requireInt($this->id, 'Outbox ID');
        $this->updateHistory(self::DELETED);

        $this->deleted = 1;

        $deleted = $this->db()
            ->update([
                'deleted' => 1,
                'update_history' => $this->update_history
            ])
            ->table('outbox')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($deleted) ? $deleted : false;
    }

    /**
     * Logs a new item to the history text.
     */
    private function updateHistory(string $type)
    {
        $update = date('ymdhis').' '.$type;

        $this->update_history = $this->update_history
            ? sprintf("%s\n%s", $update, $this->update_history)
            : $update;
    }
}
