<?php

namespace App\Server;

use App\Log
  , Exception
  , Ratchet\ConnectionInterface
  , Guzzle\Http\Message\Response
  , Ratchet\Http\HttpServerInterface
  , Guzzle\Http\Message\RequestInterface;

class WebServer implements HttpServerInterface
{
    private $log;
    private $loop;

    public function __construct( Log $log, $config )
    {
        $this->config = $config;
        $this->log = $log->getLogger();
    }

    /**
     * A new web connection was opened. This is a catchall route handler
     * that serves up static content from the client app directory. All
     * we want to do is check if the requested path exists as a file in
     * the web folder. If so, serve it, otherwise return a 404.
     * @param ConnectionInterface $conn
     * @param RequestInterface $request
     */
    public function onOpen( ConnectionInterface $conn, RequestInterface $request = NULL )
    {
        $this->log->debug(
            "New web connection opened from #". $conn->resourceId );
        $path = $request->getPath();

        // Check if the file exists in our client directory. If the path
        // is just "/" then serve the index.html file.
        if ( substr( $path, -1 ) === "/" ) {
            $path .= "index.html";
        }

        if ( file_exists( BASEPATH . '/client'. $path ) ) {
            return $this->serveFile( $conn, BASEPATH . '/client'. $path );
        }

        return $this->show404( $conn );
    }

    public function onClose( ConnectionInterface $conn )
    {
        $this->log->debug(
            "Closing web connection to #". $conn->resourceId );
    }

    public function onError( ConnectionInterface $conn, Exception $e )
    {
        $this->log->notice(
            "Error encountered from web connection: ". $e->getMessage() );
        $conn->close();
    }

    public function onMessage( ConnectionInterface $conn, $msg ) {}

    /**
     * Serves a static file to the client.
     * @param ConnectionInterface $conn
     * @param string $path
     */
    private function serveFile( ConnectionInterface $conn, $path )
    {
        $response = new Response(
            200, [
                "X-Powered-By" => APP_NAME
            ],
            file_get_contents( $path ));

        $conn->send( $response );
        $conn->close();
    }

    /**
     * Display a 404 page.
     * @param ConnectionInterface $conn
     */
    private function show404( ConnectionInterface $conn )
    {
        $response = new Response(
            404, [
                "X-Powered-By" => APP_NAME
            ],
            sprintf(
                "<h1>%s</h1></center><hr>%s %s",
                "404 Not Found",
                APP_NAME,
                APP_VERSION
            ));

        $conn->send( $response );
        $conn->close();
    }
}