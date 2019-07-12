<?php

namespace App;

use Error;
use Exception;
use Monolog\Logger;
use App\Log\CLIHandler;
use League\CLImate\CLImate;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use App\Exceptions\LogPathNotWriteable as LogPathNotWriteableException;

class Log
{
    // Console writer dependency for interactive mode
    private $cli;
    // Path to where log files live
    private $path;
    // Minimum level for logging messges
    private $level;
    // Log configuration
    private $config;
    // The Monolog handler
    private $logger;
    // Whether to write the stack traces
    private $stackTrace;
    // If we're running in interative mode
    private $interactive;

    public function __construct(CLImate $cli, array $config, bool $interactive = false)
    {
        $this->cli = $cli;
        $this->config = $config;
        $this->interactive = $interactive;
        $this->parseConfig($config, $interactive);
    }

    public function init()
    {
        // Set the error and exception handler here
        @set_error_handler([$this, 'errorHandler']);
        @set_exception_handler([$this, 'exceptionHandler']);

        $this->checkLogPath($this->interactive, $this->path ?: '');
        $this->createLog($this->config, $this->interactive);
    }

    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Exception | Error $exception
     */
    public function exceptionHandler($exception)
    {
        if ($this->stackTrace) {
            $this->getLogger()->critical(
                $exception->getMessage().
                PHP_EOL.
                $exception->getTraceAsString());
        } else {
            $this->getLogger()->critical($exception->getMessage());
        }
    }

    public function errorHandler(
        int $severity,
        string $message,
        string $filename,
        int $lineNo
    ) {
        if (! (error_reporting() & $severity)) {
            return;
        }

        switch ($severity) {
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

        if ($this->isSuppressed($message)) {
            return;
        }

        $e = new Exception("$message on line $lineNo of $filename");

        if ($this->stackTrace) {
            $this->getLogger()->$logMethod(
                $e->getMessage().PHP_EOL.$e->getTraceAsString());
        } else {
            $this->getLogger()->$logMethod($e->getMessage());
        }
    }

    /**
     * Write an error exception to the console or log depending
     * on the environment.
     *
     * @param Exception $e
     */
    public function displayError(Exception $e)
    {
        if (true === $this->interactive) {
            $this->cli->boldRedBackgroundBlack($e->getMessage());
            $this->cli->dim('[Err#'.$e->getCode().']');

            if ($this->config['stacktrace']) {
                $this->cli->br()->comment($e->getTraceAsString())->br();
            }
        } else {
            if ($this->config['stacktrace']) {
                $this->getLogger()->addError(
                    $e->getMessage().PHP_EOL.$e->getTraceAsString());
            } else {
                $this->getLogger()->addError($e->getMessage());
            }
        }
    }

    private function parseConfig(array $config, bool $interactive)
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

        $this->path = (true === $interactive)
            ? null
            : $this->preparePath($config['path']);
        $level = (true === $interactive)
            ? $config['level']['cli']
            : $config['level']['file'];
        $this->level = isset($levels[$level])
            ? $levels[$level]
            : Logger::WARNING;
        $this->stackTrace = 1 === (int) $config['stacktrace'];
    }

    /**
     * Checks if the log path is writeable by the user.
     *
     * @param bool $interactive
     * @param string $path
     *
     * @throws LogPathNotWriteableException
     *
     * @return bool
     */
    public static function checkLogPath(bool $interactive, string $path)
    {
        if ($interactive) {
            return true;
        }

        $logPath = DIRECTORY_SEPARATOR === substr($path, 0, 1)
            ? dirname($path)
            : BASEPATH;

        if (! is_writeable($logPath)) {
            throw new LogPathNotWriteableException($logPath);
        }
    }

    /**
     * Returns an absolute URL from a possible relative one.
     *
     * @param string $path
     *
     * @return string
     */
    public static function preparePath(string $path)
    {
        if (DIRECTORY_SEPARATOR === substr($path, 0, 1)) {
            return $path;
        }

        return BASEPATH.DIRECTORY_SEPARATOR.$path;
    }

    private function createLog(array $config, bool $interactive)
    {
        // Create and configure a new logger
        $log = new Logger($config['name']);

        if (true === $interactive) {
            $handler = new CLIHandler($this->cli, $this->level);
        } else {
            $handler = new RotatingFileHandler(
                $this->path,
                $maxFiles = 0,
                $this->level,
                $bubble = true
            );
        }

        // Allow line breaks and stack traces, and don't show
        // empty context arrays
        $formatter = new LineFormatter;
        $formatter->includeStacktraces();
        $formatter->allowInlineLineBreaks();
        $formatter->ignoreEmptyContextAndExtra();
        $handler->setFormatter($formatter);
        $log->pushHandler($handler);

        // Store the log internally
        $this->logger = $log;
    }

    /**
     * There are certain notices that are raised during the mail
     * header parsing that need to be suppressed.
     *
     * @param string $message Message to check against
     *
     * @return bool
     */
    private function isSuppressed(string $message)
    {
        $suppressList = [
            'Error while sending STMT_',
            'stream_select(): unable to select',
            'Unknown: Unexpected characters at end of address'
        ];

        foreach ($suppressList as $string) {
            if (0 === strpos($message, $string)) {
                return true;
            }
        }

        return false;
    }
}
