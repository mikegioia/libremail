<?php

namespace App\Model;

use PDO;
use stdClass;
use App\Model;
use Zend\Mail\Address;
use App\Exceptions\ValidationException;
use Zend\Mail\Exception\InvalidArgumentException;

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
    public $reply_to;
    public $attempts;
    public $parent_id;
    public $text_html;
    public $account_id;
    public $text_plain;
    public $created_at;
    public $updated_at;
    public $update_history;

    const CREATED = 'created';
    const UPDATED = 'updated';

    /**
     * Designed to take in POST data and save defaults.
     */
    public function __construct(
        Account $account,
        array $data,
        Message $message = null)
    {
        $this->to = $data['to'] ?? [];
        $this->cc = $data['cc'] ?? [];
        $this->bcc = $data['bcc'] ?? [];
        $this->subject = $data['subject'] ?? '';
        $this->text_plain = $data['text_plain'] ?? '';

        if (isset($data['send_draft'])) {
            $this->draft = true;
        }

        // Account fields
        $this->account_id = $account->id;
        $this->from = $account->name
            ? sprintf('%s <%s>', $account->name, $account->email)
            : $account->email;

        // If a message came in, set the message as the parent
        if ($message) {
            $this->parent_id = $message->id;
        }
    }

    /**
     * Store a new mesage in the outbox.
     */
    public function save()
    {
        $this->validate();
        $this->updateHistory($this->id ? self::CREATED : self::UPDATED);

        $data = [
            'from' => $this->from,
            'draft' => $this->draft ? 1 : 0,
            'parent_id' => $this->parent_id,
            'account_id' => $this->account_id,
            'to' => implode(', ', $this->to),
            'cc' => implode(', ', $this->cc),
            'bcc' => implode(', ', $this->bcc),
            'text_plain' => $this->text_plain,
            'update_history' => $this->update_history
        ];

        if ($this->id) {
            $this->db()
                ->update($data)
                ->table('outbox')
                ->where('id', '=', $this->id)
                ->execute();
        } else {
            $newOutboxId = $this->db()
                ->insert(array_keys($data))
                ->into('outbox')
                ->values(array_values($data))
                ->execute();

            if (! $newOutboxId) {
                throw new Exception('Failed adding message to Outbox');
            }

            $this->id = $newOutboxId;
        }

        return $this;
    }

    /**
     * Validate the message data.
     *
     * @throws ValidationException
     */
    public function validate()
    {
        $e = new ValidationException;

        if (! $this->to) {
            $e->addError('to', 'To');
        }

        foreach (['to', 'cc', 'bcc'] as $field) {
            $this->validateAddresses($field, $e);
        }

        if (! strlen(trim($this->text_plain))) {
            $e->addError('text_plain', 'Message');
        }

        if ($e->hasError() && ! $this->draft) {
            throw $e;
        }
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

    /**
     * Checks if an address field contains proper addresses.
     */
    private function validateAddresses(string $field, ValidationException $e)
    {
        $addresses = [];

        foreach ($this->$field as $address) {
            $address = $this->validAddress($address);

            if (! $address) {
                $e->addError($field, null, 'Malformed email address!');
                continue;
            }

            $addresses[] = $address;
        }

        $this->$field = $addresses;
    }

    private function validAddress(string $address)
    {
        try {
            return (Address::fromString($address))->toString();
        }
        catch (InvalidArgumentException $e) {
            return false;
        }
    }
}
