<?php

namespace App\Exceptions;

class NoAccounts extends \Exception
{
    public $code = 2010;
    public $message = "No active email accounts exist in the database.";
}