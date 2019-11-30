<?php

namespace Fn;

use DateTime;
use App\Expects;
use DateInterval;

/**
 * Looks for a value in an object or array by key
 * and either returns that value or the specified
 * default.
 *
 * @param mixes $object
 * @param string $key
 * @param mixed $default
 */
function get($object, $key, $default = null)
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
function intEq($int1, $int2)
{
    return (int) $int1 === (int) $int2;
}

/**
 * Safe string equality test.
 *
 * @param mixed $str1
 * @param mixed $str2
 *
 * @return bool
 */
function strEq($str1, $str2)
{
    return (string) $str1 === (string) $str2;
}

/**
 * Formats a count of bytes into a readable size.
 *
 * @param int $bytes
 * @param int $precision
 *
 * @return string
 */
function formatBytes($bytes, $precision = 2)
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
 * @param float $value
 */
function percent($value, $precision = 2)
{
    return round($value * 100, $precision).'%';
}

/**
 * Pluralizes a word given the count of items.
 *
 * @param string $word
 * @param int $count
 *
 * @return string
 */
function plural($word, $count)
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
 * @param array $array
 * @param string $key
 *
 * @return array
 */
function reindex($array, $key)
{
    $new = [];

    foreach ($array as $item) {
        $new[$item[$key]] = $item;
    }

    return $new;
}

/**
 * Returns a string like 1:30 PM corresponding to the
 * number of minutes from now.
 *
 * @param int $minutes
 * @param string $format
 *
 * @return string
 */
function timeFromNow($minutes, $format = 'g:i a')
{
    $time = new DateTime;
    $time->add(new DateInterval('PT'.$minutes.'M'));

    return $time->format($format);
}

function unixFromNow($minutes)
{
    $time = new DateTime;
    $time->add(new DateInterval('PT'.$minutes.'M'));

    return $time->getTimestamp();
}

/**
 * Wrapper for Expects assertion library.
 */
function expects($data)
{
    return new Expects($data);
}

/**
 * Look for a string in an array of possibilities.
 */
function contains($subject, $list)
{
    foreach ($list as $item) {
        if (false !== strpos($subject, $item)) {
            return true;
        }
    }

    return false;
}

/**
 * Cleans a subject line of extra characters.
 */
function cleanSubject(string $subject)
{
    $subject = trim(
        preg_replace(
            "/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i",
            '',
            $subject
        ));

    return trim($subject, '[]()');
}
