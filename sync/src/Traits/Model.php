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
}