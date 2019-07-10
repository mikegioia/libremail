<?php

namespace App\Model;

use PDO;
use App\Model;

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
}
