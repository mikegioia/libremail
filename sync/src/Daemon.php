<?php

namespace App;

use Exception
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
    // Stored to send signals to
    private $syncProcess;
    // Ratchet websocket server
    private $webServerProcess;

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
        }

        if ( $this->halt === TRUE ) {
            return;
        }

        $syncProcess = new Process( BASEPATH .'/sync -s' );

        // When the sync process exits, we want to alert the
        // daemon. This is to restart the sync upon crash or
        // to handle the error.
        $syncProcess->on( 'exit', function ( $exitCode, $termSignal ) {
            $this->emitter->dispatch( EV_SYNC_EXITED );
        });

        // Start the sync engine immediately
        $this->loop->addTimer( 0.001, function ( $timer ) use ( $syncProcess ) {
            $syncProcess->start( $timer->getLoop() );
            $syncProcess->stdout->on( 'data', function ( $output ) {
                echo $output;
            });
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
        }

        if ( $this->halt === TRUE ) {
            return;
        }

        $webServerProcess = new Process( BASEPATH .'/server' );

        // When the server process exits, we want to alert the
        // daemon. This is to restart the server upon crash or
        // to handle the error.
        $webServerProcess->on( 'exit', function ( $exitCode, $termSignal ) {
            $this->emitter->dispatch( EV_SERVER_EXITED );
        });

        // @TODO
        // IS THIS SPAWNING ???
        echo "Starting Web Server\n";

        // Start the web server immediately
        $this->loop->addTimer( 0.001, function ( $timer ) use ( $webServerProcess ) {
            $webServerProcess->start( $timer->getLoop() );
            $webServerProcess->stdout->on( 'data', function ( $output ) {
                echo $output;
            });
        });

        $this->webServerProcess = $webServerProcess;
    }

    public function halt()
    {
        $this->halt = TRUE;

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
}