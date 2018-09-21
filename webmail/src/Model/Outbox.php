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
        array $data = [],
        Message $message = null)
    {
        $this->subject = $data['subject'] ?? '';
        $this->text_plain = $data['text_plain'] ?? '';
        $this->to = $this->parseAddresses($data['to'] ?? []);
        $this->cc = $this->parseAddresses($data['cc'] ?? []);
        $this->bcc = $this->parseAddresses($data['bcc'] ?? []);

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

    public function getById(int $id)
    {
        $message = $this->db()
            ->select()
            ->from('outbox')
            ->where('id', '=', $id)
            ->execute()
            ->fetchObject();

        return new self($this->account, $message ?: []);
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
        }
        elseif (! $this->isEmpty()) {
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

        if (! array_filter($this->to)) {
            $e->addError('to', null, 'Specify at least one recipient', 0);
        }

        foreach (['to', 'cc', 'bcc'] as $field) {
            $this->validateAddresses($field, $e);
        }

        if ($e->hasError() && ! $this->draft) {
            throw $e;
        }
    }

    /**
     * Determines if this message is completely empty.
     */
    public function isEmpty()
    {
        return ! $this->to
            && ! $this->cc
            && ! $this->bcc
            && ! $this->subject
            && ! $this->text_plain;
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
     * @param array|string $addresses This should always come in
     *   as an array from the client, but since it's POST data we
     *   we can't type hint it.
     */
    private function parseAddresses($addresses)
    {
        $addresses = is_array($addresses)
            ? $addresses
            : explode(',', $addresses);
        $addresses = array_map('trim', $addresses);

        return array_values(array_filter($addresses));
    }

    /**
     * Checks if an address field contains proper addresses.
     */
    private function validateAddresses(string $field, ValidationException $e)
    {
        $addresses = [];

        foreach ($this->$field as $i => $address) {
            $parsedAddress = $this->validAddress($address);

            if (! $parsedAddress) {
                $e->addError($field, null, 'Malformed email address!', $i);
                $addresses[] = $address;
            } else {
                $addresses[] = $parsedAddress;
            }
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
