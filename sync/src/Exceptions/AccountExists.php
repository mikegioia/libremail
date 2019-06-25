<?php

namespace App\Exceptions;

use Exception;

class AccountExists extends Exception
{
    public $code = EXC_ACCOUNT_EXISTS;
    public $message = "The account for '%s' already exists.";

    public function __construct(string $email)
    {
        $this->message = sprintf($this->message, $email);
    }
}
