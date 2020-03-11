<?php

namespace App\Traits;

use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use Belt\Belt;
use App\Exceptions\NotFound as NotFoundException;
use App\Exceptions\Validation as ValidationException;

trait Model
{
    public function getClass()
    {
        return get_class();
    }

    public function getId()
    {
        return (int) $this->id;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Returns a string containing the PDO error info if there
     * is any.
     *
     * @return string
     */
    public function getError()
    {
        $errorInfo = $this->db()->errorInfo();

        if (strlen($errorInfo[2])) {
            return sprintf(
                '[%s -- %s]: %s',
                $errorInfo[0],
                $errorInfo[1],
                $errorInfo[2]
            );
        }

        return '';
    }

    /**
     * Turns stdClass SQL objects into model objects.
     *
     * @param array $objects
     * @param string $modelClass
     *
     * @return array
     */
    public function populate(array $objects, string $modelClass = null)
    {
        $modelObjects = [];
        $modelClass = $modelClass ?: $this->getClass();

        if (! is_array($objects)) {
            return new $modelClass($objects);
        }

        foreach ($objects as $object) {
            $modelObjects[] = new $modelClass($object);
        }

        return $modelObjects;
    }

    public function isValidFlag(int $flag)
    {
        return in_array($flag, [0, 1]);
    }

    public function requireInt($number, string $name)
    {
        if (! Belt::isNumber($number)) {
            throw new ValidationException("$name needs to be an integer.");
        }
    }

    public function requireString($string, string $name)
    {
        if (! Belt::isString($string)) {
            throw new ValidationException("$name needs to be a string.");
        }
    }

    public function requireArray($values, string $name)
    {
        if (! is_array($values) || ! Belt::size($values)) {
            throw new ValidationException(
                "$name needs to be an array with values."
            );
        }
    }

    public function requireValue($value, array $collection)
    {
        if (! Belt::contains($collection, $value)) {
            throw new ValidationException(
                "$value must be one of ".implode(', ', $collection).'.'
            );
        }
    }

    public function handleNotFound($result, string $type, bool $fail)
    {
        if (! $result && true === $fail) {
            throw new NotFoundException($type);
        }
    }

    /**
     * Updates any values referenced in keys to be valid flags
     * for the database. A flag is 1 or 0.
     *
     * @param array $data
     * @param array $keys
     */
    private function updateFlagValues(array &$data, array $keys)
    {
        foreach ($keys as $key) {
            if (! isset($data[$key])) {
                continue;
            }

            $data[$key] = (int) $data[$key];
        }
    }

    /**
     * Updates any values referenced in keys to be valid UTF8
     * strings.
     *
     * @param array $data
     * @param array $keys
     */
    private function updateUtf8Values(array &$data, array $keys)
    {
        foreach ($keys as $key) {
            if (! isset($data[$key])
                || true === mb_check_encoding($data[$key])
            ) {
                continue;
            }

            $data[$key] = @iconv('UTF-8', 'UTF-8//IGNORE', $data[$key]);
        }
    }

    /**
     * @param bool | int $updated Response from update operation
     *
     * @throws DatabaseUpdateException
     */
    protected function errorHandle($updated)
    {
        if (! Belt::isNumber($updated)) {
            throw new DatabaseUpdateException(
                MESSAGE,
                $this->getError()
            );
        }
    }
}
