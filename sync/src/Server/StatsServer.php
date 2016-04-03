<?php

namespace App\Server;

use App\Log
  , Exception
  , App\Command
  , SplObjectStorage
  , React\Stream\Stream
  , Ratchet\ConnectionInterface
  , React\EventLoop\LoopInterface
  , Ratchet\MessageComponentInterface;

class StatsServer implements MessageComponentInterface
{
    private $log;
    private $loop;
    private $message;
    private $clients;
    private $isReading;
    // Streams
    private $read;
    private $write;

    public function __construct ( Log $log, LoopInterface $loop )
    {
        $this->loop = $loop;
        $this->log = $log->getLogger();
        $this->clients = new SplObjectStorage;

        // Set up the STDIN and STDOUT streams
        $this->setupInputStreams();
    }

    public function broadcast( $message )
    {
        foreach ( $this->clients as $client ) {
            $client->send( $message );
        }
    }

    public function onOpen( ConnectionInterface $conn )
    {
        $this->log->debug( "New socket connection opened from #". $conn->resourceId );
        $this->clients->attach( $conn );
    }

    public function onClose( ConnectionInterface $conn )
    {
        $this->log->debug( "Closing socket connection to #". $conn->resourceId );
        $this->clients->detach( $conn );
    }

    public function onError( ConnectionInterface $conn, Exception $e )
    {
        $this->log->notice(
            "Error encountered from socket connection: ". $e->getMessage() );
        $conn->close();
    }

    public function onMessage( ConnectionInterface $from, $message )
    {
        $this->log->debug( "New socket message from #{$from->resourceId}: $message" );
        $this->processMessage( $message );
    }

    private function setupInputStreams()
    {
        $this->read = new Stream( STDIN, $this->loop );

        // The data can come in JSON encoded. If so, we want to
        // detect this format and keep reading until we reach the
        // end of the JSON stream.
        $this->read->on( 'data', function ( $data ) {
            $message = $this->processMessage( $data );
        });

        $this->write = new Stream( STDOUT, $this->loop );
    }

    private function processMessage( $message )
    {
        if ( substr( $message, 0, 1 ) === "{" ) {
            $this->message = "";
            $this->isReading = TRUE;
        }

        if ( $this->isReading ) {
            $this->message .= $message;

            if ( substr( $message, -1 ) === "}" ) {
                $this->isReading = FALSE;
                $this->broadcast( $this->message );
                $this->message = NULL;
            }

            return;
        }

        if ( ! $this->write ) {
            return;
        }

        // A text command came in. This could be something like
        // restart the sync (wake up), force-fetch the stats,
        // shutdown the sync, etc. Send this to the daemon. If it's
        // a valid command, then send it to STDOUT.
        $command = new Command();

        if ( $command->isValid( $message ) ) {
            $this->write->write( $message );
        }
    }
}