<?php

namespace App\Server;

use App\Log;
use App\Util;
use Exception;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;

class WebServer implements HttpServerInterface
{
    private $log;

    public function __construct(Log $log)
    {
        $this->log = $log->getLogger();
    }

    /**
     * A new web connection was opened. This is a catchall route handler
     * that serves up static content from the client app directory. All
     * we want to do is check if the requested path exists as a file in
     * the web folder. If so, serve it, otherwise return a 404.
     *
     * @param RequestInterface $request
     */
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        $this->log->debug(
            'New web connection opened from #'.Util::get($conn, 'resourceId', '?')
        );

        $uri = $request->getUri();
        $path = $uri->getPath();

        // Check if the file exists in our client directory. If the path
        // is just "/" then serve the index.html file.
        if ('/' === substr($path, -1)) {
            $path .= 'index.html';
        }

        if (file_exists(BASEPATH.'/client'.$path)) {
            return $this->serveFile($conn, BASEPATH.'/client'.$path);
        }

        return $this->show404($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->log->debug(
            'Closing web connection to #'.Util::get($conn, 'resourceId', '?')
        );
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $this->log->notice(
            'Error encountered from web connection: '.$e->getMessage()
        );

        $conn->close();
    }

    public function onMessage(ConnectionInterface $conn, $msg)
    {
        // Empty
    }

    /**
     * Serves a static file to the client.
     *
     * @param string $path
     */
    private function serveFile(ConnectionInterface $conn, $path)
    {
        $response = new Response(
            200, [
                'X-Powered-By' => APP_NAME,
                'Content-Type' => $this->getContentType($path)
            ],
            file_get_contents($path)
        );

        $conn->send($response);
        $conn->close();
    }

    /**
     * Display a 404 page.
     */
    private function show404(ConnectionInterface $conn)
    {
        $response = new Response(
            404, [
                'X-Powered-By' => APP_NAME,
                'Content-Type' => 'text/html'
            ],
            sprintf(
                '<h1>%s</h1></center><hr>%s %s',
                '404 Not Found',
                APP_NAME,
                APP_VERSION
            )
        );

        $conn->send($response);
        $conn->close();
    }

    /**
     * Try to get the content type by file extension.
     *
     * @return string
     */
    private function getContentType(string $path)
    {
        $pathInfo = pathinfo($path);
        $types = [
            'css' => 'text/css',
            'html' => 'text/html',
            'js' => 'text/javascript',
            'woff' => 'application/font-woff',
            'woff2' => 'application/font-woff'
        ];

        return isset($types[$pathInfo['extension']])
            ? $types[$pathInfo['extension']]
            : 'text/plain';
    }
}
