<?php

namespace App\Exceptions;

use Exception;

class NotFound extends Exception
{
    public $code = EXC_DB_NOTFOUND;
    public $message = 'The requested %s could not be found.';
    public $messageId = 'The requested %s [#%s] could not be found.';

    public function __construct(string $type, int $id = null)
    {
        $this->message = $id
            ? sprintf($this->messageId, $type, $id)
            : sprintf($this->message, $type);
    }
}
