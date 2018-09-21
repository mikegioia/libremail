<?php

namespace App\Exceptions;

class ValidationException extends \Exception
{
    private $errors = [];

    public function addError(string $key, string $label, string $message = null)
    {
        $this->errors[$key] = $message ?: $label.' must not be empty';
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
