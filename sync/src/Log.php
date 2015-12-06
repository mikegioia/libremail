<?php

namespace App;

use Monolog\Logger
  , App\Log\CLIHandler
  , League\CLImate\CLImate
  , Monolog\Handler\StreamHandler
  , Monolog\Formatter\LineFormatter
  , Monolog\Handler\RotatingFileHandler
  , App\Exceptions\LogPathNotWriteable as LogPathNotWriteableException;

class Log
{
    // Console writer dependency for interactive mode
    private $cli;
    // Path to where log files live
    private $path;
    // Minimum level for logging messges
    private $level;
    // The Monolog handler
    private $logger;
    // Whether to write the stack traces
    private $stackTrace;

    public function __construct( CLImate $cli, array $config, $interactive = FALSE )
    {
        $this->cli = $cli;
        $this->parseConfig( $config, $interactive );
        $this->checkLogPath( $interactive );
        $this->createLog( $config, $interactive );

        // Set the error and exception handler here
        @set_error_handler([ $this, 'errorHandler' ]);
        @set_exception_handler([ $this, 'exceptionHandler' ]);
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function exceptionHandler( $exception )
    {
        if ( $this->stackTrace ) {
            $this->getLogger()->critical(
                $exception->getMessage() .
                PHP_EOL .
                $exception->getTraceAsString() );
        }
        else {
            $this->getLogger()->critical( $exception->getMessage() );
        }
    }

    public function errorHandler( $severity, $message, $filename, $lineNo )
    {
        if ( ! ( error_reporting() & $severity ) ) {
            return;
        }

        switch ( $severity ) {
            case E_USER_ERROR:
            case E_USER_WARNING:
            case E_WARNING:
                $logMethod = 'warning';
                break;
            case E_USER_NOTICE:
            case E_NOTICE:
            case @E_STRICT:
                $logMethod = 'notice';
                break;
            case @E_RECOVERABLE_ERROR:
            default:
                $logMethod = 'error';
                break;
        }

        if ( $this->isSuppressed( $message ) ) {
            return;
        }

        $e = new \Exception( "$message on line $lineNo of $filename" );

        if ( $this->stackTrace ) {
            $this->getLogger()->$logMethod(
                $e->getMessage() . PHP_EOL . $e->getTraceAsString() );
        }
        else {
            $this->getLogger()->$logMethod( $e->getMessage() );
        }
    }

    private function parseConfig( array $config, $interactive )
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

        $this->path = ( $interactive === TRUE )
            ? NULL
            : $config[ 'path' ];
        $level = ( $interactive === TRUE )
            ? $config[ 'level' ][ 'cli' ]
            : $config[ 'level' ][ 'file' ];
        $this->level = ( isset( $levels[ $level ] ) )
            ? $levels[ $level ]
            : Logger::WARNING;
        $this->stackTrace = $config[ 'stacktrace' ];
    }

    /**
     * Checks if the log path is writeable by the user.
     * @throws LogPathNotWriteableException
     * @return boolean
     */
    private function checkLogPath( $interactive )
    {
        if ( $interactive ) {
            return TRUE;
        }

        $logPath = ( substr( $this->path, 0, 1 ) !== DIRECTORY_SEPARATOR )
            ? __DIR__
            : $this->path;

        if ( ! is_writeable( $logPath ) ) {
            throw new LogPathNotWriteableException;
        }
    }

    private function createLog( array $config, $interactive )
    {
        // Create and configure a new logger
        $log = new Logger( $config[ 'name' ] );

        if ( $interactive === TRUE ) {
            $handler = new CLIHandler( $this->cli, $this->level );
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

    /**
     * There are certain notices that are raised during the mail
     * header parsing that need to be suppressed.
     * @param string $message Message to check against
     * @return boolean
     */
    private function isSuppressed( $message )
    {
        $suppressList = [
            'Unknown: Unexpected characters at end of address'
        ];

        foreach ( $suppressList as $string ) {
            if ( strpos( $message, $string ) === 0 ) {
                return TRUE;
            }
        }

        return FALSE;
    }
}