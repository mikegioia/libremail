<?php

namespace App;

use Countable;
use DateInterval;
use DateTime;

class Util
{
    /**
     * Looks for a value in an object or array by key
     * and either returns that value or the specified
     * default.
     *
     * @param mixed $object
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get($object, string $key, $default = null)
    {
        if (is_array($object)
            && array_key_exists($key, $object)
        ) {
            return $object[$key];
        }

        if (is_object($object)
            && array_key_exists($key, (array) $object)
        ) {
            return $object->$key;
        }

        return $default;
    }

    /**
     * Safe integer equality test.
     *
     * @param mixed $int1
     * @param mixed $int2
     *
     * @return bool
     */
    public static function intEq(int $int1, int $int2)
    {
        return $int1 === $int2;
    }

    /**
     * Safe string equality test.
     *
     * @param mixed $str1
     * @param mixed $str2
     *
     * @return bool
     */
    public static function strEq(string $str1, string $str2)
    {
        return $str1 === $str2;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isNumber($value)
    {
        return is_integer($value) || is_float($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isString($value)
    {
        return is_string($value);
    }

    /**
     * Formats a count of bytes into a readable size.
     *
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        // Choose one of the following 2 calculations
        $bytes /= pow(1024, $pow);
        // $bytes /= ( 1 << ( 10 * $pow ) );

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Converts a decimal to a formatted percent.
     *
     * @return string
     */
    public static function percent(float $value, int $precision = 2)
    {
        return round($value * 100, $precision).'%';
    }

    /**
     * Pluralizes a word given the count of items.
     *
     * @return string
     */
    public static function plural(string $word, int $count)
    {
        if (1 === $count) {
            return $word;
        } elseif ('s' === substr($word, -1)) {
            return $word;
        } elseif ('y' === substr($word, -1)) {
            return substr($word, 0 - 1).'ies';
        }

        return $word.'s';
    }

    /**
     * Takes in an array and reindexes the array but the value
     * stored in $key.
     *
     * @return array
     */
    public static function reindex(array $array, string $key)
    {
        $new = [];

        foreach ($array as $item) {
            $new[$item[$key]] = $item;
        }

        return $new;
    }

    /**
     * Look for a string in an array of possibilities.
     *
     * @return bool
     */
    public static function contains(string $subject, array $list)
    {
        return in_array($subject, $list, true);
    }

    /**
     * @param array|Countable $value
     *
     * @return int|null
     */
    public static function size($value)
    {
        if (is_array($value) || ($value instanceof Countable)) {
            return count($value);
        }

        return null;
    }

    /**
     * Returns a string like 1:30 PM corresponding to the
     * number of minutes from now.
     *
     * @return string
     */
    public static function timeFromNow(int $minutes, string $format = 'g:i a')
    {
        $time = new DateTime;
        $time->add(new DateInterval('PT'.$minutes.'M'));

        return $time->format($format);
    }

    /**
     * @return int
     */
    public static function unixFromNow(int $minutes)
    {
        $time = new DateTime;
        $time->add(new DateInterval('PT'.$minutes.'M'));

        return $time->getTimestamp();
    }

    /**
     * Wrapper for Expects assertion library.
     *
     * @param array|object $data
     *
     * @return Expects
     */
    public static function expects($data)
    {
        return new Expects($data);
    }

    /**
     * Cleans a subject line of extra characters.
     *
     * @return string
     */
    public static function cleanSubject(string $subject)
    {
        $subject = trim(
            preg_replace(
                "/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i",
                '',
                $subject
            ));

        return trim($subject, '[]()');
    }
}
