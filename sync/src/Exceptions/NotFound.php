<?php

namespace App\Exceptions;

use Exception;

class NotFound extends Exception
{
    public $code = EXC_DB_NOTFOUND;
    public $message = 'The requested %s could not be found.';

    public function __construct(string $type)
    {
        $this->message = sprintf($this->message, $type);
    }
}
