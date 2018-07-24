<?php

namespace App\Task;

use ReflectionClass;

abstract class AbstractTask
{
    /**
     * @var string comes from Task class constants
     */
    protected $type;

    /**
     * Constructor takes in a data array and sets the class variables
     * based on the data keys.
     *
     * @param array|object $data
     */
    public function __construct($data)
    {
        $data = (array) $data;
        $refClass = new ReflectionClass($this);

        foreach ($refClass->getProperties() as $property) {
            $name = $property->name;

            if (isset($data[$name])) {
                $this->$name = $data[$name];
            }
        }
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Each task implements this function to perform it's action.
     */
    abstract public function run();
}
