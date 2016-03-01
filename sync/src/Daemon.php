<?php

namespace App;

use Exception
  , App\Events\StatsEvent
  , App\Console\DaemonConsole
  , React\ChildProcess\Process
  , React\EventLoop\LoopInterface
  , App\Exceptions\Terminate as TerminateException
  , Symfony\Component\EventDispatcher\EventDispatcher as Emitter;

class Daemon
{
    // Flag to not attempt to restart process
    private $halt;
    // Reference to the React event loop
    private $loop;
    private $config;
    private $emitter;
    private $console;
    // Used for internal message passing
    private $message;
    private $isReading;
    // Stored to send signals to
    private $syncProcess;
    // Ratchet websocket server
    private $webServerProcess;
    // References to true PIDs
    private $processPids = [];

    const PROC_SYNC = 'sync';
    const PROC_SERVER = 'server';
    const MESSAGE_PID = 'pid';
    const MESSAGE_STATS = 'stats';

    public function __construct(
        LoopInterface $loop,
        Emitter $emitter,
        DaemonConsole $console,
        Array $config )
    {
        $this->loop = $loop;
        $this->config = $config;
        $this->emitter = $emitter;
        $this->console = $console;
    }

    public function init()
    {
        // Set the error and exception handler here
        @set_error_handler([ $this, 'errorHandler' ]);
        @set_exception_handler([ $this, 'exceptionHandler' ]);
    }

    public function startSync()
    {
        if ( $this->syncProcess ) {
            $this->syncProcess->close();
            $this->syncProcess = NULL;
            $this->processPids[ self::PROC_SYNC ] = NULL;
        }

        if ( $this->halt === TRUE ) {
            return;
        }

        // @TODO replace -s with -b
        $syncProcess = new Process( BASEPATH .'/sync -s -e' );

        // When the sync process exits, we want to alert the
        // daemon. This is to restart the sync upon crash or
        // to handle the error.
        $syncProcess->on( 'exit', function ( $exitCode, $termSignal ) {
            $this->processPids[ self::PROC_SYNC ] = NULL;
            $this->emitter->dispatch( EV_SYNC_EXITED );
        });

        // Start the sync engine immediately
        $this->loop->addTimer( 0.001, function ( $timer ) use ( $syncProcess ) {
            $syncProcess->start( $timer->getLoop() );
            $syncProcess->stdout->on( 'data', function ( $output ) {
                // If JSON came back, we have a message to parse. Run
                // it through our message handler. Otherwise forward the
                // output to STDOUT.
                $this->processMessage( $output, self::PROC_SYNC );
            });
        });

        // Every 10 seconds signal the sync process to get statistics.
        // Only do this if the webserver is running.
        $this->loop->addPeriodicTimer( 10, function ( $timer ) use ( $syncProcess ) {
            if ( isset( $this->processPids[ self::PROC_SYNC ] )
                && $this->webServerProcess )
            {
                posix_kill( $this->processPids[ self::PROC_SYNC ], SIGUSR2 );
            }
        });

        $this->syncProcess = $syncProcess;
    }

    public function startWebServer()
    {
        if ( ! $this->console->webServer ) {
            return;
        }

        if ( $this->webServerProcess ) {
            $this->webServerProcess->close();
            $this->webServerProcess = NULL;
            $this->processPids[ self::PROC_SERVER ] = NULL;
        }

        if ( $this->halt === TRUE ) {
            return;
        }

        $webServerProcess = new Process( BASEPATH .'/server' );

        // When the server process exits, we want to alert the
        // daemon. This is to restart the server upon crash or
        // to handle the error.
        $webServerProcess->on( 'exit', function ( $exitCode, $termSignal ) {
            $this->processPids[ self::PROC_SERVER ] = NULL;
            $this->emitter->dispatch( EV_SERVER_EXITED );
        });

        // @TODO
        // IS THIS SPAWNING ???
        echo "Starting Web Server\n";

        // Start the web server immediately
        $this->loop->addTimer( 0.001, function ( $timer ) use ( $webServerProcess ) {
            $webServerProcess->start( $timer->getLoop() );
            $webServerProcess->stdout->on( 'data', function ( $output ) {
                $this->processMessage( $output, self::PROC_SERVER );
            });
        });

        $this->webServerProcess = $webServerProcess;
    }

    public function halt()
    {
        $this->halt = TRUE;
        $this->processPids = [];

        if ( $this->syncProcess ) {
            $this->syncProcess->terminate( SIGQUIT );
        }

        if ( $this->webServerProcess ) {
            $this->webServerProcess->terminate( SIGQUIT );
        }

        throw new TerminateException;
    }

    public function exceptionHandler( $exception )
    {
        if ( ! $this->config[ 'log' ][ 'exception' ] ) {
            return;
        }

        $message = $exception->getMessage();

        if ( $this->config[ 'log' ][ 'stacktrace' ] ) {
            $message .= PHP_EOL . $exception->getTraceAsString();
        }

        fwrite( STDERR, $message );
    }

    public function errorHandler( $severity, $message, $filename, $lineNo )
    {
        if ( ! ( error_reporting() & $severity )
            || ! $this->config[ 'log' ][ 'error' ]
            || ! $this->isSuppressed( $message ) )
        {
            return;
        }

        return $this->exceptionHandler(
            new Exception(
                "$message on line $lineNo of $filename"
            ));
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
            'unable to select [4]: Interrupted system call'
        ];

        foreach ( $suppressList as $string ) {
            if ( strpos( $message, $string ) === 0 ) {
                return TRUE;
            }
        }

        return FALSE;
    }

    private function processMessage( $message, $process )
    {
        if ( substr( $message, 0, 1 ) === "{" ) {
            $this->message = "";
            $this->isReading = TRUE;
        }

        if ( $this->isReading ) {
            $this->message .= $message;

            if ( substr( $message, -1 ) === "}" ) {
                $this->isReading = FALSE;
                $message = @json_decode( $this->message );
                $this->message = NULL;
                $this->handleMessage( $message, $process );
            }

            return;
        }

        fwrite( STDOUT, $message );
    }

    /**
     * Reads in a JSON message from one of the child processes.
     * The message expects certain fields to be set:
     *   type: 'pid' or 'stats'
     * @param stdClass $message
     * @param string $process
     */
    private function handleMessage( $message, $process )
    {
        switch ( $message->type ) {
            case self::MESSAGE_PID:
                $this->processPids[ $process ] = $message->pid;
                break;
            case self::MESSAGE_STATS:
                $this->emitter->dispatch(
                    EV_BROADCAST_STATS,
                    new StatsEvent( $message ) );
                break;
        }
    }
}