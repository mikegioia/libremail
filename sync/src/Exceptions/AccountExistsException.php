<?php

namespace App\Exceptions;

class AccountExists extends \Exception
{
    public $code = EXC_ACCOUNT_EXISTS;
    public $message = "The account for '%s' already exists.";

    function __construct( $email )
    {
        $this->message = sprintf( $this->message, $email );
    }
}