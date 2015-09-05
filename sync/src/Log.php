<?php

namespace App;

use Monolog\Logger
  , Monolog\Handler\StreamHandler;

class Log
{
    // Path to where log files live
    private $path = NULL;
    // Minimum level for logging messges
    private $level;
    // The Monolog handler
    private $logger;

    function __construct( $config, $stdout = FALSE )
    {
        // Set up lookup table for log levels
        $levels = [
            0 => Logger::EMERGENCY, // system is unusable
            1 => Logger::ALERT, // action must be taken immediately
            2 => Logger::CRITICAL, // critical conditions
            3 => Logger::ERROR, // error conditions
            4 => Logger::WARNING, // warning conditions
            5 => Logger::NOTICE, // normal but significant condition
            6 => Logger::INFO, // informational messages
            7 => Logger::DEBUG //debug-level messages
        ];

        $this->path = ( $stdout === TRUE )
            ? "php://stdout"
            : $config[ 'path' ];
        $this->level = ( isset( $levels[ $config[ 'level' ] ] ) )
            ? $levels[ $config[ 'level' ] ]
            : Logger::WARNING;

        $this->logger = new Logger( $config[ 'name' ] );
        $this->logger->pushHandler(
            new StreamHandler(
                $this->path,
                $this->level
            ));
    }

    function getLogger()
    {
        return $this->logger;
    }
}