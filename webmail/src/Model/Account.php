<?php

namespace App\Model;

use PDO;
use App\Model;

class Account extends Model
{
    public $id;
    public $email;
    public $service;
    public $password;
    public $is_active;
    public $imap_host;
    public $imap_port;
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
            ? new self(current($active))
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
}
