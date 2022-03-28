<?php

namespace App\Model;

use App\Exceptions\NotFound as NotFoundException;
use App\Model;
use App\Traits\Model as ModelTrait;
use Laminas\Mail\AddressList;
use PDO;

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
    public $failed;
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

    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';

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
            'failed' => $this->failed,
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
