<?php

namespace App\Server;

use Exception
  , App\Server
  , Ratchet\ConnectionInterface
  , Ratchet\Http\HttpServerInterface
  , Guzzle\Http\Message\RequestInterface;

class WebServer extends Server implements HttpServerInterface
{
    /**
     * A new web connection was opened. This should just return our
     * base index.html page. All other actions happen via the socket.
     */
    public function onOpen( ConnectionInterface $conn, RequestInterface $request = NULL )
    {
        $this->log->debug( "New web connection opened from #". $conn->resourceId );
        $conn->send( "Hello World" );
        $conn->close();
    }

    public function onClose( ConnectionInterface $conn )
    {
        $this->log->debug( "Closing web connection to #". $conn->resourceId );
    }

    public function onError( ConnectionInterface $conn, Exception $e )
    {
        $this->log->notice(
            "Error encountered from web connection: ". $e->getMessage() );
        $conn->close();
    }

    public function onMessage( \Ratchet\ConnectionInterface $conn, $msg ) {}
}