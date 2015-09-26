<?php

namespace App\Traits;

trait Model
{
    function getClass()
    {
        return get_class();
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

        foreach ( $objects as $object ) {
            $modelObjects[] = new $modelClass( $object );
        }

        return $modelObjects;
    }
}