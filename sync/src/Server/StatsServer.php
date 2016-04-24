<?php

namespace App\Server;

use App\Log
  , Exception
  , App\Command
  , App\Message
  , SplObjectStorage
  , React\Stream\Stream
  , Ratchet\ConnectionInterface
  , React\EventLoop\LoopInterface
  , Ratchet\MessageComponentInterface
  , App\Traits\JsonMessage as JsonMessageTrait;

class StatsServer implements MessageComponentInterface
{
    private $log;
    private $loop;
    private $message;
    private $clients;
    private $lastMessage;
    // Streams
    private $read;
    private $write;

    // For JSON message handling
    use JsonMessageTrait;

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
        $this->lastMessage = $message;

        foreach ( $this->clients as $client ) {
            $client->send( $message );
        }
    }

    public function onOpen( ConnectionInterface $conn )
    {
        $this->log->debug( "New socket connection opened from #". $conn->resourceId );
        $this->clients->attach( $conn );

        if ( $this->lastMessage ) {
            $conn->send( $this->lastMessage );
        }

        // Send a command to return the status of the sync script.
        // If the daemon gets this and the sync script isn't running,
        // then it'll trigger an event to broadcast an offline message.
        $this->write->write( Command::getMessage( Command::HEALTH ) );
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
        $this->processMessage( $message, NULL );
    }

    private function setupInputStreams()
    {
        $this->read = new Stream( STDIN, $this->loop );

        // The data can come in JSON encoded. If so, we want to
        // detect this format and keep reading until we reach the
        // end of the JSON stream.
        $this->read->on( 'data', function ( $data ) {
            $message = $this->processMessage( $data, NULL );
        });

        $this->write = new Stream( STDOUT, $this->loop );
    }

    private function processMessage( $message, $key )
    {
        if ( ! $this->write 
            || ! ($parsed = $this->parseMessage( $message )) )
        {
            return;
        }

        // A text command came in. This could be something like
        // restart the sync (wake up), force-fetch the stats,
        // shutdown the sync, etc. Send this to the daemon. If it's
        // a valid command, then send it to STDOUT.
        if ( (new Command)->isValid( $parsed ) ) {
            $this->write->write( $parsed );
        }

        // If it's a message of type 'task', execute that task
        if ( Message::isValid( $parsed ) ) {
            $message = Message::make( $parsed );

            if ( $message->getType() == Message::MESSAGE_TASK ) {
                echo "We got a task...\n";
            }
        }
    }

    private function handleMessage( $json, $key )
    {
        $this->broadcast( $json );
    }
}