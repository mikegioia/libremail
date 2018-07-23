<?php

namespace App\Exceptions;

class DatabaseInsert extends \Exception
{
    public $code = EXC_DB_INSERT;
    public $message = 'There was a problem creating this %s.';

    public function __construct($type, $errors = [])
    {
        $this->message = sprintf($this->message, $type);

        if (is_string($errors)) {
            $this->message .= " $errors";
        }
        elseif (count($errors)) {
            $this->message .= ' '.implode(PHP_EOL, $errors);
        }
    }
}
