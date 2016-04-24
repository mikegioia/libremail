<?php

namespace App\Message;

use ReflectionClass;

abstract class AbstractMessage
{
    protected $type;

    public function getType()
    {
        return $this->type;
    }

    public function toArray()
    {
        $refClass = new ReflectionClass( $this );
        $response = [
            'type' => $this->getType()
        ];

        foreach ( $refClass->getProperties() as $property ) {
            $name = $property->name;
            $response[ $name ] = $this->$name;
        }

        return $response;
    }
}