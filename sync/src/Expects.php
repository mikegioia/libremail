<?php

namespace App;

use App\Exceptions\Validation as ValidationException;

/**
 * Assertion library for checking if an object contains
 * the expected properties and types.
 */
class Expects
{
    private $data;

    /**
     * @param array|object $data
     *
     * @throws ValidationException
     */
    public function __construct($data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (! is_array($data)) {
            throw new ValidationException('Invalid expects: '.print_r($data, true));
        }

        $this->data = $data;
    }

    /**
     * Checks if the array contains the given keys.
     *
     * @throws InvalidArgumentException
     */
    public function toHave(array $keys)
    {
        $intersection = array_intersect_key(
            array_flip($keys),
            $this->data
        );

        if (count($intersection) < count($keys)) {
            $missing = array_diff($keys, array_flip($this->data));
            $message = sprintf("%s '%s' %s '%s'",
                'toHave() expects argument to contain keys',
                implode(', ', $keys),
                'but was missing',
                implode(', ', $missing)
            );

            throw new ValidationException($message);
        }

        return $this;
    }
}
