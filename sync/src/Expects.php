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
     * @param array | object $data
     */
    public function __construct($data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (! is_array($data)) {
            throw new \Exception('Invalid expects: '.print_r($data, true));
        }

        $this->data = $data;
    }

    /**
     * Checks if the array contains the given keys.
     *
     * @param array $keys
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
            $keys = implode(', ', $keys);
            $missing = implode(', ', $missing);

            throw new ValidationException(
                "toHave() expects argument to contain keys '$keys' but ".
                "was missing '$missing'"
            );
        }

        return $this;
    }
}
