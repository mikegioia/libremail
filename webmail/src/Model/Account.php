<?php

namespace App\Model;

use PDO;
use Exception;
use App\Model;
use App\Config;
use App\Exceptions\ServerException;
use App\Exceptions\DatabaseInsertException;

class Account extends Model
{
    public $id;
    public $name;
    public $email;
    public $service;
    public $password;
    public $is_active;
    public $imap_host;
    public $imap_port;
    public $smtp_host;
    public $smtp_port;
    public $imap_flags;
    public $created_at;

    public function getData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'service' => $this->service,
            'password' => $this->password,
            'is_active' => $this->is_active,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'imap_flags' => $this->imap_flags,
            'created_at' => $this->created_at
        ];
    }

    public function getActive()
    {
        return $this->db()
            ->select()
            ->from('accounts')
            ->where('is_active', '=', 1)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    public function getFirstActive()
    {
        $active = $this->getActive();

        return $active
            ? current($active)
            : null;
    }

    public function getByEmail(string $email)
    {
        $account = $this->db()
            ->select()
            ->from('accounts')
            ->where('email', '=', $email)
            ->execute()
            ->fetchObject();

        return $account
            ? new self($account)
            : null;
    }

    public function fromAddress()
    {
        return $this->name
            ? sprintf('%s <%s>', $this->name, $this->email)
            : $this->email;
    }

    public function hasFolders()
    {
        return (new Folder)->countByAccount($this->id) > 0;
    }

    /**
     * Updates the account configuration.
     */
    public function update(
        string $email,
        string $password,
        string $name,
        string $imapHost,
        int $imapPort,
        string $smtpHost,
        int $smtpPort
    ) {
        if (! $this->exists()) {
            return false;
        }

        $updated = $this->db()
            ->update([
                'name' => trim($name),
                'email' => trim($email),
                'password' => trim($password),
                'imap_host' => trim($imapHost),
                'imap_port' => trim($imapPort),
                'smtp_host' => trim($smtpHost),
                'smtp_port' => trim($smtpPort)
            ])
            ->table('accounts')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }

    /**
     * Creates a new account with the class data. This is done only
     * after the IMAP credentials are tested to be working.
     *
     * @throws ServerException
     *
     * @return Account
     */
    public function create()
    {
        // Exit if it already exists!
        if ($this->exists()) {
            return false;
        }

        $service = Config::getEmailService($this->email);
        list($imapHost, $imapPort) = Config::getImapSettings($this->email);

        if ($imapHost) {
            $this->imap_host = $imapHost;
            $this->imap_port = $imapPort;
        }

        $data = [
            'is_active' => 1,
            'service' => $service,
            'name' => trim($this->name),
            'email' => trim($this->email),
            'password' => trim($this->password),
            'imap_host' => trim($this->imap_host),
            'imap_port' => trim($this->imap_port),
            'smtp_host' => trim($this->smtp_host),
            'smtp_port' => trim($this->smtp_port),
            'created_at' => $this->utcDate()->format(DATE_DATABASE)
        ];

        try {
            // Start a transaction to prevent storing bad data
            $this->db()->beginTransaction();

            $newAccountId = $this->db()
                ->insert(array_keys($data))
                ->into('accounts')
                ->values(array_values($data))
                ->execute();

            if (! $newAccountId) {
                throw new DatabaseInsertException;
            }

            $this->db()->commit();
            // Saved to the database
        } catch (Exception $e) {
            $this->db()->rollback();

            throw new ServerException(
                'Failed creating new account. '.$e->getMessage()
            );
        }

        $this->id = $newAccountId;

        return $this;
    }
}
