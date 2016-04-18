<?php

namespace App;

use Ratchet\Wamp\WampServer
  , Ratchet\Http\OriginCheck
  , Ratchet\ComponentInterface
  , Ratchet\WebSocket\WsServer
  , Ratchet\App as BaseRatchetApp
  , Symfony\Component\Routing\Route
  , Ratchet\Http\HttpServerInterface
  , Ratchet\Wamp\WampServerInterface
  , Ratchet\MessageComponentInterface;

class RatchetApp extends BaseRatchetApp
{
    /**
     * This extends the base method by allowing a Route object to come in
     * instead of a string path.
     * @see https://github.com/ratchetphp/Ratchet/pull/239
     * @param string|Route $path The URI the client will connect to or
     *   a valid Route object
     * @param ComponentInterface $controller Your application to server
     *   for the route. If not specified, assumed to be for a WebSocket
     * @param array $allowedOrigins An array of hosts allowed to connect
     *   (same host by default), ['*'] for any
     * @param string $httpHost Override the $httpHost variable provided
     *   in the __construct
     * @return ComponentInterface|WsServer
     */
    public function route( $path, ComponentInterface $controller, array $allowedOrigins = [], $httpHost = NULL )
    {
        if ( $controller instanceof HttpServerInterface
            || $controller instanceof WsServer)
        {
            $decorated = $controller;
        }
        elseif ( $controller instanceof WampServerInterface ) {
            $decorated = new WsServer( new WampServer( $controller ) );
        }
        elseif ( $controller instanceof MessageComponentInterface ) {
            $decorated = new WsServer( $controller );
        }
        else {
            $decorated = $controller;
        }

        if ( $httpHost === NULL ) {
            $httpHost = $this->httpHost;
        }

        $allowedOrigins = array_values( $allowedOrigins );

        if ( 0 === count( $allowedOrigins ) ) {
            $allowedOrigins[] = $httpHost;
        }

        if ( '*' !== $allowedOrigins[ 0 ] ) {
            $decorated = new OriginCheck( $decorated, $allowedOrigins );
        }

        // Allow origins in flash policy server
        if ( empty( $this->flashServer ) === FALSE ) {
            foreach ( $allowedOrigins as $allowedOrgin ) {
                $this->flashServer->app->addAllowedAccess( $allowedOrgin, $this->port );
            }
        }

        // Allow $path to also be a Route
        $route = NULL;

        if ( $path instanceof Route ) {
            $route = $path;
            $route->setHost( $httpHost );
            $route->setDefault( '_controller', $decorated );
            $route->addRequirements([ 'Origin' => $this->httpHost ]);
        }
        else {
            $route = new Route(
                $path,
                [ '_controller' => $decorated ],
                [ 'Origin' => $this->httpHost ],
                [],
                $httpHost );
        }

        $this->routes->add( 'rr-' . ++$this->_routeCounter, $route );

        return $decorated;
    }
}