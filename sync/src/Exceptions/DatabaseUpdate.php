<?php

namespace App\Exceptions;

use Exception;

class DatabaseUpdate extends Exception
{
    public $code = EXC_DB_UPDATE;
    public $message = 'There was a problem updating this %s.';

    /**
     * @param string|array $errors
     */
    public function __construct(string $type, $errors = [])
    {
        $this->message = sprintf($this->message, $type);

        if (is_string($errors)) {
            $this->message .= " $errors";
        } elseif (count($errors)) {
            $this->message .= ' '.implode(PHP_EOL, $errors);
        }
    }
}
