<?php

namespace App\Traits;

trait Model
{
    function getClass()
    {
        return get_class();
    }

    function getId()
    {
        return (int) $this->id;
    }

    function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Turns stdClass SQL objects into model objects.
     * @param array $objects
     * @return array
     */
    function populate( $objects, $modelClass = NULL )
    {
        $modelObjects = [];
        $modelClass = ( $modelClass ) ?: $this->getClass();

        if ( ! is_array( $objects ) ) {
            return new $modelClass( $object );
        }

        foreach ( $objects as $object ) {
            $modelObjects[] = new $modelClass( $object );
        }

        return $modelObjects;
    }
}