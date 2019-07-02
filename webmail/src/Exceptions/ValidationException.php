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

        $this->setMessage($this->getMessageString());
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getMessageString()
    {
        $messages = [];
        $errors = $this->getErrors();

        foreach ($this->getErrors() as $key => $errors) {
            if (is_array($errors)) {
                $messages = array_merge($messages, $errors);
            } else {
                $messages[] = $errors;
            }
        }

        return implode('<br>', $messages);
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
