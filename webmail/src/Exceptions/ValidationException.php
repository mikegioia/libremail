<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private $errors = [];

    public function addError(
        string $key,
        string $label = null,
        string $message = null,
        int $index = null
    ) {
        $message ?: ($label ?: 'Field').' must not be empty';

        if (! is_null($index)) {
            if (! isset($this->errors[$key]) || ! is_array($this->errors[$key])) {
                $this->errors[$key] = [];
            }

            $this->errors[$key][$index] = $message;
        } else {
            $this->errors[$key] = $message;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasError()
    {
        return count($this->errors) > 0;
    }
}
