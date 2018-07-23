<?php

namespace App\Exceptions;

class NotFound extends \Exception
{
    public $code = EXC_DB_NOTFOUND;
    public $message = 'The requested %s could not be found.';

    public function __construct($type)
    {
        $this->message = sprintf($this->message, $type);
    }
}
