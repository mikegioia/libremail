<?php

namespace App\Traits;

use App\Exceptions\NotFound as NotFoundException;
use App\Exceptions\Validation as ValidationException;
use App\Util;

trait Model
{
    public function getClass()
    {
        return get_class();
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return isset($this->id)
            ? (int) $this->id
            : null;
    }

    /**
     * @return string|null
     */
    public function getCreatedAt()
    {
        return isset($this->created_at)
            ? (string) $this->created_at
            : null;
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

    /**
     * @param mixed $flag
     */
    public function isValidFlag($flag)
    {
        return Util::contains((int) $flag, [0, 1]);
    }

    /**
     * @param mixed $flag
     */
    public function requireValidFlag($flag, string $name): void
    {
        if (! $this->isValidFlag($flag)) {
            throw new ValidationException("$name needs to be 0 or 1.");
        }
    }

    /**
     * @param mixed $number
     */
    public function requireInt($number, string $name): void
    {
        if (! Util::isNumber($number)) {
            throw new ValidationException("$name needs to be an integer.");
        }
    }

    /**
     * @param mixed $string
     */
    public function requireString($string, string $name): void
    {
        if (! Util::isString($string)) {
            throw new ValidationException("$name needs to be a string.");
        }
    }

    /**
     * @param mixed $values
     */
    public function requireArray($values, string $name)
    {
        if (! is_array($values) || ! Util::size($values)) {
            throw new ValidationException("$name needs to be an array with values.");
        }
    }

    /**
     * @param mixed $value
     */
    public function requireValue($value, array $collection)
    {
        if (! Util::contains($value, $collection)) {
            $message = sprintf('%s must be one of %s.',
                $value,
                implode(', ', $collection)
            );

            throw new ValidationException($message);
        }
    }

    /**
     * @param mixed $result
     */
    public function handleNotFound($result, string $type, bool $fail)
    {
        if (! $result && true === $fail) {
            throw new NotFoundException($type);
        }
    }

    /**
     * Updates any values referenced in keys to be valid flags
     * for the database. A flag is 1 or 0.
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
}
