<?php

namespace App\Model;

use App\Exceptions\NotFound as NotFoundException;
use App\Exceptions\Validation as ValidationException;
use App\Model;
use App\Traits\Model as ModelTrait;
use Laminas\Mail\AddressList;
use PDO;

class Outbox extends Model
{
    use ModelTrait;

    public $account_id;
    public $attempts;
    public $bcc;
    public $cc;
    public $created_at;
    public $deleted;
    public $draft;
    public $failed;
    public $from;
    public $id;
    public $locked;
    public $parent_id;
    public $reply_to;
    public $sent;
    public $subject;
    public $text_html;
    public $text_plain;
    public $thread_id;
    public $to;
    public $update_history;
    public $updated_at;

    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';

    public function getData()
    {
        return [
            'account_id' => $this->account_id,
            'attempts' => $this->attempts,
            'bcc' => $this->bcc,
            'cc' => $this->cc,
            'created_at' => $this->created_at,
            'deleted' => $this->deleted,
            'draft' => $this->draft,
            'failed' => $this->failed,
            'from' => $this->from,
            'id' => $this->id,
            'locked' => $this->locked,
            'parent_id' => $this->parent_id,
            'reply_to' => $this->reply_to,
            'sent' => $this->sent,
            'subject' => $this->subject,
            'text_html' => $this->text_html,
            'text_plain' => $this->text_plain,
            'thread_id' => $this->thread_id,
            'to' => $this->to,
            'update_history' => $this->update_history,
            'updated_at' => $this->updated_at
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

    public function markSent()
    {
        $this->requireInt($this->id, 'Outbox ID');
        $this->updateHistory(self::SENT);

        $this->sent = 1;
        $this->failed = 0;

        $updated = $this->db()
            ->update([
                'sent' => 1,
                'failed' => 0,
                'update_history' => $this->update_history
            ])
            ->table('outbox')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }

    public function markFailed(string $message)
    {
        $this->requireInt($this->id, 'Outbox ID');
        $this->updateHistory(self::FAILED, $message);

        $this->sent = 0;
        $this->failed = 1;

        $updated = $this->db()
            ->update([
                'sent' => 0,
                'failed' => 1,
                'update_history' => $this->update_history
            ])
            ->table('outbox')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
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
     * Returns an array of email address, wrapped in '<' and '>'.
     * The names are stripped to comply with various mailserver
     * address format issues. See 5.5.2 from gsmtp.
     *
     * @return AddressList
     */
    public function getAddressList(string $fieldName)
    {
        $this->requireValue($fieldName, [
            'to', 'cc', 'bcc'
        ]);

        $addressList = new AddressList;
        $addresses = explode(',', $this->$fieldName);

        foreach ($addresses as $address) {
            list($name, $email) = $this->getAddressNameParts($address);
            $addressList->add($email, $name);
        }

        return $addressList;
    }

    private function getAddressNameParts(string $address)
    {
        $parts = explode('<', $address, 2);
        $count = count($parts);

        if (1 === $count) {
            $name = '';
            $email = trim($parts[0], ' >');
        } else {
            $name = trim($parts[0]);
            $email = trim($parts[1], ' <>');
        }

        return [$name, $email];
    }

    /**
     * Logs a new item to the history text.
     */
    private function updateHistory(string $type, string $message = null)
    {
        $update = date('ymdhis').' '.$type;

        if ($message) {
            $update .= '['.$message.']';
        }

        $this->update_history = $this->update_history
            ? sprintf("%s\n%s", $update, $this->update_history)
            : $update;
    }
}
