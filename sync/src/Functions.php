<?php

namespace Fn;

use DateTime
  , DateInterval;

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

function formatBytes( $bytes, $precision = 2 )
{
    $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );
    // Choose one of the following 2 calculations
    $bytes /= pow( 1024, $pow );
    // $bytes /= ( 1 << ( 10 * $pow ) );

    return round( $bytes, $precision ) .' '. $units[ $pow ];
}

function plural( $word, $count )
{
    if ( $count === 1 ) {
        return $word;
    }
    elseif ( substr( $word, -1 ) === 's' ) {
        return $word;
    }
    elseif ( substr( $word, -1 ) === 'y' ) {
        return substr( $word, 0 -1 ) ."ies";
    }

    return $word ."s";
}

/**
 * Returns a string like 1:30 PM corresponding to the
 * number of minutes from now.
 */
function timeFromNow( $minutes, $format = 'g:i a' )
{
    $time = new DateTime;
    $time->add( new DateInterval( 'PT'. $minutes .'M' ) );

    return $time->format( $format );
}