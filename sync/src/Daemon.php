<?php

namespace App;

use App\Log
  , Exception
  , App\Command
  , App\Message
  , App\Events\MessageEvent
  , App\Console\DaemonConsole
  , App\Message\HealthMessage
  , React\ChildProcess\Process
  , App\Message\AbstractMessage
  , React\EventLoop\LoopInterface
  , App\Traits\JsonMessage as JsonMessageTrait
  , App\Exceptions\Terminate as TerminateException
  , App\Exceptions\BadCommand as BadCommandException
  , Symfony\Component\EventDispatcher\EventDispatcher as Emitter;

class Daemon
{
    private $log;
    // Flag to not attempt to restart process
    private $halt;
    // Reference to the React event loop
    private $loop;
    private $config;
    private $emitter;
    private $console;
    private $command;
    // Flag if there are no accounts in the database
    private $noAccounts;
    // Stored to send signals to
    private $syncProcess;
    // Log of diagnostic test results
    private $diagnostics;
    // Ratchet websocket server
    private $webServerProcess;
    // References to true PIDs
    private $processPids = [];
    private $processRestartInterval = [];

    // For JSON message handling
    use JsonMessageTrait;

    const PROC_SYNC = 'sync';
    const PROC_SERVER = 'server';

    public function __construct(
        Log $log,
        LoopInterface $loop,
        Emitter $emitter,
        DaemonConsole $console,
        Command $command,
        Array $config )
    {
        $this->loop = $loop;
        $this->config = $config;
        $this->emitter = $emitter;
        $this->console = $console;
        $this->command = $command;
        $this->log = $log->getLogger();
        $this->processRestartInterval = [
            PROC_SYNC => $config[ 'restart_interval' ][ PROC_SYNC ],
            PROC_SERVER => $config[ 'restart_interval' ][ PROC_SERVER ]
        ];
    }

    public function startSync()
    {
        if ( $this->halt === TRUE ) {
            return;
        }

        if ( $this->syncProcess ) {
            $this->syncProcess->close();
            $this->syncProcess = NULL;
            $this->processPids[ PROC_SYNC ] = NULL;
        }

        $syncProcess = new Process( BASEPATH . EXEC_SYNC );

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
                // If JSON came back, we have a message to parse. Run
                // it through our message handler. Otherwise forward the
                // output to STDOUT.
                $this->processMessage( $output, PROC_SYNC );
            });
        });

        // Every 10 seconds signal the sync process to get statistics.
        // Only do this if the webserver is running.
        $this->loop->addPeriodicTimer( 10, function ( $timer ) use ( $syncProcess ) {
            if ( isset( $this->processPids[ PROC_SYNC ] )
                && $this->webServerProcess )
            {
                posix_kill( $this->processPids[ PROC_SYNC ], SIGUSR2 );
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
            $this->processPids[ PROC_SERVER ] = NULL;
        }

        if ( $this->halt === TRUE ) {
            return;
        }

        $webServerProcess = new Process( BASEPATH . EXEC_SERVER );

        // When the server process exits, we want to alert the
        // daemon. This is to restart the server upon crash or
        // to handle the error.
        $webServerProcess->on( 'exit', function ( $exitCode, $termSignal ) {
            $this->emitter->dispatch( EV_SERVER_EXITED );
        });

        // Start the web server immediately
        $this->loop->addTimer( 0.001, function ( $timer ) use ( $webServerProcess ) {
            $webServerProcess->start( $timer->getLoop() );
            $webServerProcess->stdout->on( 'data', function ( $output ) {
                $this->processMessage( $output, PROC_SERVER );
            });
        });

        $this->webServerProcess = $webServerProcess;
    }

    /**
     * Emits an event to restart a process after an interval
     * of time has passed, plus some decay value. The decay is
     * used to not thrash a reconnect if we have networking
     * problems.
     * @param string $process
     */
    public function restartWithDecay( $process, $event )
    {
        $decay = $this->config[ 'decay' ][ $process ];
        $restartMax = $this->config[ 'restart_max' ][ $process ];
        $currentInterval = $this->processRestartInterval[ $process ];
        $nextInterval = ( $decay * $currentInterval < $restartMax )
            ? $decay * $currentInterval
            : $restartMax;

        $this->loop->addTimer(
            $nextInterval,
            function ( $timer ) use ( $event ) {
                $this->emitter->dispatch( $event );
            });

        // Update the restart interval for next time. At some point,
        // this needs to reset back to the starting interval.
        // @TODO
        $this->processRestartInterval[ $process ] = $nextInterval;
    }

    /**
     * Sends a SIGCONT to the sync process to wake it up.
     */
    public function continueSync()
    {
        if ( isset( $this->processPids[ PROC_SYNC ] ) ) {
            posix_kill( $this->processPids[ PROC_SYNC ], SIGCONT );
        }
    }

    /**
     * Sends a SIGPWR to st sync process to stop it.
     */
    public function stopSync()
    {
        if ( isset( $this->processPids[ PROC_SYNC ] ) ) {
            posix_kill( $this->processPids[ PROC_SYNC ], SIGPWR );
        }
    }

    /**
     * Sends a SIGUSR2 to the sync process to get a stats update.
     */
    public function pollStats()
    {
        if ( isset( $this->processPids[ PROC_SYNC ] ) ) {
            posix_kill( $this->processPids[ PROC_SYNC ], SIGUSR2 );
        }
    }

    /**
     * Sends a message to the server process to forward to any clients.
     * @param AbstractMessage $message
     */
    public function broadcast( AbstractMessage $message )
    {
        if ( ! $this->webServerProcess ) {
            return FALSE;
        }

        // Resume the stdin stream, send the message and then pause
        // it again.
        $this->webServerProcess->stdin->resume();
        $this->webServerProcess->stdin->write(
            Message::packJson(
                $message->toArray()
            ));
        $this->webServerProcess->stdin->pause();
    }

    /**
     * Sends a health report for the sync process and app.
     */
    public function broadcastHealth()
    {
        $this->broadcast(
            new HealthMessage(
                $this->diagnostics, [
                    PROC_SYNC => ( isset( $this->processPids[ PROC_SYNC ] ) )
                        ? TRUE
                        : FALSE,
                    PROC_SERVER => ( isset( $this->processPids[ PROC_SERVER ] ) )
                        ? TRUE
                        : FALSE
                ],
                $this->noAccounts
            ));
    }

    public function halt()
    {
        $this->halt = TRUE;

        if ( isset( $this->processPids[ PROC_SYNC ] ) ) {
            posix_kill( $this->processPids[ PROC_SYNC ], SIGQUIT );
        }

        if ( isset( $this->processPids[ PROC_SERVER ] ) ) {
            posix_kill( $this->processPids[ PROC_SERVER ], SIGQUIT );
        }

        $this->processPids = [];
    }

    private function processMessage( $message, $process )
    {
        if ( ! ($parsed = $this->parseMessage( $message, $process )) ) {
            return;
        }

        // If the message was a command from a sub-process, then
        // handle that command. Otherwise print this message.
        if ( $this->handleCommand( $parsed ) ) {
            return;
        }

        fwrite( STDOUT, $parsed );
    }

    /**
     * Reads in a JSON message from one of the child processes.
     * The message expects certain fields to be set depending on
     * the type.
     * @param string $json
     * @param string $process
     */
    private function handleMessage( $json, $process )
    {
        if ( ! Message::isValid( $json ) ) {
            $this->log->addNotice( "Invalid message sent to Daemon: $json" );
            return FALSE;
        }

        try {
            $message = Message::make( $json );
        }
        catch ( Exception $e ) {
            return FALSE;
        }

        switch ( $message->getType() ) {
            case Message::PID:
                $this->processPids[ $process ] = $message->pid;
                break;
            case Message::STATS:
                if ( $message->accounts ):
                    $this->noAccounts = false;
                endif;
                // no break, broadcast
            case Message::ERROR:
            case Message::ACCOUNT:
            case Message::NOTIFICATION:
                $this->emitter->dispatch(
                    EV_BROADCAST_MSG,
                    new MessageEvent( $message ) );
                break;
            case Message::NO_ACCOUNTS:
                $this->noAccounts = true;
                break;
            case Message::DIAGNOSTICS:
                $this->diagnostics = $message->tests;
                break;
        }
    }

    /**
     * Reads in a message to determine if the message is a
     * command from a subprocess. If it is, pass this command
     * off to our handler library and return true.
     * @param string $message
     * @return boolean
     */
    private function handleCommand( $message )
    {
        if ( $this->command->isValid( $message ) ) {
            // Ignore these but log them
            try {
                $this->command->run( $message );
            }
            catch ( BadCommandException $e ) {
                $this->log->addNotice( $e->getMessage() );
                return FALSE;
            }

            return TRUE;
        }

        return FALSE;
    }
}