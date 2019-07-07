<?php

namespace App\Model;

use PDO;
use Parsedown;
use App\Model;
use App\Session;
use Zend\Mail\Address;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseInsertException;
use App\Exceptions\DatabaseUpdateException;
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
    public $deleted;
    public $subject;
    public $reply_to;
    public $attempts;
    public $parent_id;
    public $text_html;
    public $text_plain;
    public $account_id;
    public $send_after;
    public $created_at;
    public $updated_at;
    public $update_history;

    private $parent;
    private $account;

    // Lazy-loaded and cached
    private $unreadCount;

    const CREATED = 'created';
    const UPDATED = 'updated';

    /**
     * Designed to take in POST data and save defaults.
     */
    public function __construct(
        Account $account = null,
        Message $message = null,
        array $data = null
    ) {
        $this->parent = $message;

        if ($account) {
            $this->account = $account;
            $this->account_id = $this->account->id;
            $this->from = $account->name
                ? sprintf('%s <%s>', $account->name, $account->email)
                : $account->email;
        }

        // If a message came in, set the message as the parent
        if ($this->parent) {
            $this->parent_id = $message->id;
        }

        if ($data) {
            $this->setData($data);
            // Converts strings to arrays
            $this->to = $this->parseAddresses($this->to);
            $this->cc = $this->parseAddresses($this->cc);
            $this->bcc = $this->parseAddresses($this->bcc);
        }
    }

    public function setPostData(array $data)
    {
        $this->id = $data['id'] ?? 0;
        $this->subject = $data['subject'] ?? '';
        $this->text_plain = $data['text_plain'] ?? '';
        $this->text_html = $this->getHtml();
        $this->to = $this->parseAddresses($data['to'] ?? []);
        $this->cc = $this->parseAddresses($data['cc'] ?? []);
        $this->bcc = $this->parseAddresses($data['bcc'] ?? []);

        // When saving the message, it defaults to 'draft'
        $this->draft = 1;

        // If the user approves the preview, remove draft flag
        if (isset($data['send_outbox'])) {
            $this->draft = 0;
        }

        return $this;
    }

    /**
     * Copies data from a message into this outbox message.
     */
    public function copyMessageData(Message $message, bool $isDraft = true)
    {
        $this->from = $message->from;
        $this->draft = $isDraft ? 1 : 0;
        $this->subject = $message->subject;
        $this->text_plain = $message->text_plain;
        $this->text_html = $this->getHtml();
        $this->to = $this->parseAddresses($message->to);
        $this->cc = $this->parseAddresses($message->cc);
        $this->bcc = $this->parseAddresses($message->bcc);

        return $this;
    }

    public function getById(int $id)
    {
        $data = $this->db()
            ->select()
            ->from('outbox')
            ->where('id', '=', $id)
            ->where('deleted', '=', 0)
            ->execute()
            ->fetch();

        return new self($this->account, null, $data ?: null);
    }

    /**
     * Store a new mesage in the outbox.
     *
     * @throws DatabaseInsertException
     * @throws DatabaseUpdateException
     */
    public function save(bool $notifyOnError = false)
    {
        $this->validate($notifyOnError);
        $this->updateHistory($this->id ? self::UPDATED : self::CREATED);

        $data = [
            'from' => $this->from,
            'subject' => $this->subject,
            'draft' => $this->draft ? 1 : 0,
            'parent_id' => $this->parent_id,
            'account_id' => $this->account_id,
            'text_html' => $this->text_html,
            'text_plain' => $this->text_plain,
            'to' => $this->addressString($this->to),
            'cc' => $this->addressString($this->cc),
            'bcc' => $this->addressString($this->bcc),
            'update_history' => $this->update_history,
            'send_after' => $this->send_after ?? null
        ];

        if ($this->id) {
            $updated = $this->db()
                ->update($data)
                ->table('outbox')
                ->where('id', '=', $this->id)
                ->execute();

            if (! is_numeric($updated)) {
                throw new DatabaseUpdateException(
                    "Failed updating outbox message {$this->id}"
                );
            }
        } elseif (! $this->isEmpty()) {
            $newOutboxId = $this->db()
                ->insert(array_keys($data))
                ->into('outbox')
                ->values(array_values($data))
                ->execute();

            if (! $newOutboxId) {
                throw new DatabaseInsertException(
                    'Failed adding message to outbox'
                );
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
    public function validate(bool $notifyOnError = false)
    {
        $e = new ValidationException;

        foreach (['to', 'cc', 'bcc'] as $field) {
            $this->validateAddresses($field, $e);
        }

        if (! array_filter($this->to)) {
            $e->addError('to', null, 'Specify at least one recipient.', 0);
        }

        if ($e->hasError()) {
            if ($this->draft && true === $notifyOnError) {
                Session::notify($e->getMessage(), Session::ERROR);
            } elseif (! $this->draft) {
                throw $e;
            }
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

    public function isDraft()
    {
        return 1 === (int) $this->draft;
    }

    public function exists()
    {
        return is_numeric($this->id)
            && 0 !== (int) $this->id
            && 1 !== (int) $this->deleted;
    }

    /**
     * Returns text_plain markdown converted to HTML.
     *
     * @return string
     */
    public function getHtml()
    {
        return (new Parsedown())->text($this->text_plain);
    }

    /**
     * Returns a human-readable string of names to display
     * in a short list.
     *
     * @return string
     */
    public function getNames()
    {
        $names = [];
        $to = $this->parseAddresses($this->to);
        $cc = $this->parseAddresses($this->cc);
        $bcc = $this->parseAddresses($this->bcc);

        if ($to) {
            $names[] = 'To: '.implode(', ', $this->getNamesFromAddresses($to));
        }

        if ($cc) {
            $names[] = 'CC: '.implode(', ', $this->getNamesFromAddresses($cc));
        }

        if ($bcc) {
            $names[] = 'BCC: '.implode(', ', $this->getNamesFromAddresses($bcc));
        }

        return $names
            ? implode(', ', $names)
            : '';
    }

    /**
     * Returns the count of unread/active outbox messages.
     * Caches this locally.
     *
     * @throws ValidationException
     *
     * @return int
     */
    public function getUnreadCount()
    {
        if (! is_null($this->unreadCount)) {
            return $this->unreadCount;
        }

        if (! $this->account_id) {
            throw new ValidationException(
                'Account ID required when querying outbox unread'
            );
        }

        $result = $this->db()
            ->select(['count(id) as count'])
            ->from('outbox')
            ->where('sent', '=', 0)
            ->where('draft', '=', 0)
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $this->account->id)
            ->execute()
            ->fetch();

        $this->unreadCount = $result['count'] ?? 0;

        return $this->unreadCount;
    }

    public function getActive()
    {
        if (! $this->account_id) {
            throw new ValidationException(
                'Account ID required when querying outbox unread'
            );
        }

        return $this->db()
            ->select()
            ->from('outbox')
            ->where('sent', '=', 0)
            ->where('draft', '=', 0)
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $this->account->id)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    /**
     * @throws NotFoundException
     */
    public function softDelete()
    {
        if (! $this->exists()) {
            throw new NotFoundException;
        }

        $deleted = $this->db()
            ->update(['deleted' => 1])
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

    /**
     * @param array | string $addresses this should always come in
     *   as an array from the client, but since it's POST data we
     *   we can't type hint it
     */
    private function parseAddresses($addresses)
    {
        $addresses = is_array($addresses)
            ? $addresses
            : explode(',', $addresses);
        $addresses = array_map('trim', $addresses);

        return array_values(array_filter($addresses));
    }

    private function getNamesFromAddresses(array $addresses)
    {
        return array_map(function ($address) {
            $address = trim($address);
            $pos = strpos($address, '<');

            if (false !== $pos && $pos > 0) {
                return trim(substr($address, 0, $pos));
            }

            return trim($address, '<> ');
        }, $addresses);
    }

    private function addressString(array $addresses)
    {
        return implode(', ', array_filter($addresses));
    }

    /**
     * Checks if an address field contains proper addresses.
     */
    private function validateAddresses(string $field, ValidationException $e)
    {
        $addresses = [];

        foreach ($this->$field as $i => $address) {
            // Skip blank ones but maintain index association
            if (! strlen(trim($address))) {
                $addresses[] = '';
                continue;
            }

            $parsedAddress = $this->validAddress($address);

            if (! $parsedAddress) {
                $e->addError($field, null, 'Malformed email address!', $i);
                $addresses[] = $address;
            } else {
                if (0 === strpos($parsedAddress, '<')) {
                    $parsedAddress = trim($parsedAddress, '<>');
                }

                $addresses[] = $parsedAddress;
            }
        }

        $this->$field = $addresses;
    }

    private function validAddress(string $address)
    {
        try {
            return (Address::fromString($address))->toString();
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }
}
