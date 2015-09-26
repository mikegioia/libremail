<?php

namespace App\Exceptions;

class NoAccounts extends \Exception
{
    public $code = EXC_NO_ACCOUNTS;
    public $message = "No active email accounts exist in the database.";
}