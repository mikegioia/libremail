<?php

namespace App\Exceptions;

class AccountExists extends \Exception
{
    public $code = 2011;
    public $message = "The account for '%s' already exists.";

    function __construct( $email )
    {
        $this->message = sprintf( $this->message, $email );
    }
}