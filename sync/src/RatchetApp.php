<?php

namespace App;

use Ratchet\App as BaseRatchetApp;
use Ratchet\ComponentInterface;
use Ratchet\Http\HttpServerInterface;
use Ratchet\Http\OriginCheck;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServer;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Routing\Route;

class RatchetApp extends BaseRatchetApp
{
    /**
     * This extends the base method by allowing a Route object to come in
     * instead of a string path.
     *
     * @see https://github.com/ratchetphp/Ratchet/pull/239
     *
     * @param string|Route $path The URI the client will connect to or
     *   a valid Route object
     * @param ComponentInterface $controller Your application to server
     *   for the route. If not specified, assumed to be for a WebSocket
     * @param array $allowedOrigins An array of hosts allowed to connect
     *   (same host by default), ['*'] for any
     * @param string $httpHost Override the $httpHost variable provided
     *   in the __construct
     *
     * @return ComponentInterface|WsServer
     */
    public function route($path, ComponentInterface $controller, array $allowedOrigins = [], $httpHost = null)
    {
        if ($controller instanceof HttpServerInterface) {
            $decorated = $controller;
        } elseif ($controller instanceof WampServerInterface) {
            $decorated = new WsServer(new WampServer($controller));
            $decorated->enableKeepAlive($this->_server->loop);
        } elseif ($controller instanceof MessageComponentInterface) {
            $decorated = new WsServer($controller);
            $decorated->enableKeepAlive($this->_server->loop);
        } else {
            $decorated = $controller;
        }

        if (null === $httpHost) {
            $httpHost = $this->httpHost;
        }

        $allowedOrigins = array_values($allowedOrigins);

        if (0 === count($allowedOrigins)) {
            $allowedOrigins[] = $httpHost;
        }

        if ('*' !== $allowedOrigins[0]
            && $decorated instanceof MessageComponentInterface
        ) {
            $decorated = new OriginCheck($decorated, $allowedOrigins);
        }

        // Allow $path to also be a Route
        $route = null;

        if ($path instanceof Route) {
            $route = $path;
            $route->setHost($httpHost);
            $route->setDefault('_controller', $decorated);
            $route->addRequirements(['Origin' => $this->httpHost]);
        } else {
            $route = new Route(
                $path,
                ['_controller' => $decorated],
                ['Origin' => $this->httpHost],
                [],
                $httpHost
            );
        }

        $this->routes->add('rr-'.++$this->_routeCounter, $route);

        return $decorated;
    }
}
