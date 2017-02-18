<?php

namespace App\Traits;

use Belt\Belt
  , App\Exceptions\NotFound as NotFoundException
  , App\Exceptions\Validation as ValidationException;

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
     * @return string
     */
    public function getError()
    {
        $errorInfo = $this->db()->errorInfo();

        if ( strlen( $errorInfo[ 2 ] ) ) {
            return sprintf(
                "[%s -- %s]: %s",
                $errorInfo[ 0 ],
                $errorInfo[ 1 ],
                $errorInfo[ 2 ] );
        }

        return "";
    }

    /**
     * Turns stdClass SQL objects into model objects.
     * @param array $objects
     * @return array
     */
    public function populate( $objects, $modelClass = NULL )
    {
        $modelObjects = [];
        $modelClass = ( $modelClass ) ?: $this->getClass();

        if ( ! is_array( $objects ) ) {
            return new $modelClass( $objects );
        }

        foreach ( $objects as $object ) {
            $modelObjects[] = new $modelClass( $object );
        }

        return $modelObjects;
    }

    public function isValidFlag( $flag )
    {
        return in_array( (int) $flag, [ 0, 1 ] );
    }

    public function requireInt( $number, $name )
    {
        if ( ! Belt::isNumber( $number ) ) {
            throw new ValidationException(
                "$name needs to be an integer." );
        }
    }

    public function requireString( $string, $name )
    {
        if ( ! Belt::isString( $string ) ) {
            throw new ValidationException(
                "$name needs to be a string." );
        }
    }

    public function handleNotFound( $result, $type, $fail )
    {
        if ( ! $result && $fail === TRUE )
        {
            throw new NotFoundException( $type );
        }
    }

    /**
     * Updates any values referenced in keys to be valid flags
     * for the database. A flag is 1 or 0.
     * @param array $data
     * @param array $keys
     */
    private function updateFlagValues( &$data, $keys )
    {
        foreach ( $keys as $key ) {
            if ( ! isset( $data[ $key ] ) ) {
                continue;
            }

            $data[ $key ] = (int) $data[ $key ];
        }
    }

    /**
     * Updates any values referenced in keys to be valid UTF8
     * strings.
     * @param array $data
     * @param array $keys
     */
    private function updateUtf8Values( &$data, $keys )
    {
        foreach ( $keys as $key ) {
            if ( ! isset( $data[ $key ] )
                || mb_check_encoding( $data[ 'key' ] ) === TRUE )
            {
                continue;
            }

            $data[ $key ] = @iconv(
                'UTF-8',
                'UTF-8//IGNORE',
                $data[ $key ] );
        }
    }
}