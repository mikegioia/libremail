<?php

namespace Fn;

/**
 * Looks for a value in an object or array by key
 * and either returns that value or the specified
 * default.
 * @param mixes $object
 * @param string $key
 * @param mixed $default
 */
function get( $object, $key, $default = NULL )
{
    if ( is_array( $object )
        && array_key_exists( $key, $object ) )
    {
        return $object[ $key ];
    }

    if ( is_object( $object )
        && array_key_exists( $key, (array) $object ) )
    {
        return $object->$key;
    }

    return $default;
}

function intEq( $int1, $int2 )
{
    return (int) $int1 === (int) $int2;
}

function strEq( $str1, $str2 )
{
    return (string) $str1 === (string) $str2;
}