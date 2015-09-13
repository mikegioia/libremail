<?php

namespace App;

use Monolog\Logger
  , Monolog\Handler\StreamHandler
  , Monolog\Formatter\LineFormatter
  , Monolog\Handler\RotatingFileHandler
  , App\Exceptions\LogPathNotWriteable as LogPathNotWriteableException;

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
        $this->parseConfig( $config, $stdout );
        $this->checkLogPath( $stdout );
        $this->createLog( $config, $stdout );
    }

    function getLogger()
    {
        return $this->logger;
    }

    private function parseConfig( $config, $stdout )
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
    }

    /**
     * Checks if the log path is writeable by the user.
     * @throws LogPathNotWriteableException
     * @return boolean
     */
    private function checkLogPath( $stdout )
    {
        if ( $stdout ) {
            return TRUE;
        }

        $logPath = ( substr( $this->path, 0, 1 ) !== "/" )
            ? __DIR__
            : $this->path;

        if ( ! is_writeable( $logPath ) ) {
            throw new LogPathNotWriteableException;
        }
    }

    private function createLog( $config, $stdout )
    {
        // Create and configure a new logger
        $log = new Logger( $config[ 'name' ] );

        if ( $stdout === TRUE ) {
            $handler = new StreamHandler( $this->path, $this->level );
        }
        else {
            $handler = new RotatingFileHandler(
                $this->path,
                $maxFiles = 0,
                $this->level,
                $bubble = TRUE );
        }

        // Allow line breaks and stack traces, and don't show
        // empty context arrays
        $formatter = new LineFormatter;
        $formatter->includeStacktraces();
        $formatter->allowInlineLineBreaks();
        $formatter->ignoreEmptyContextAndExtra();
        $handler->setFormatter( $formatter );
        $log->pushHandler( $handler );

        // Store the log internally
        $this->logger = $log;
    }
}