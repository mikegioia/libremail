<?php

/**
 * Simple router taking the Request URI and routing the request to
 * the specified callback.
 *
 * Based entirely on Bramus Van Damme's Router:
 * https://github.com/bramus/router
 */

namespace App;

class Router
{
    // The route patterns and their handling functions
    private $routes = [];
    // The before middleware route patterns and their handling functions
    private $befores = [];
    // The function to be executed when no route has been matched
    protected $notFound;
    // Current baseroute, used for (sub)route mounting
    private $baseroute = '';
    // The Request Method that needs to be handled
    private $method = '';

    /**
     * Store a before middleware route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function before( $methods, $pattern, $fn )
    {
        $pattern = $this->baseroute . '/' . trim( $pattern, '/' );
        $pattern = ( $this->baseroute )
            ? rtrim( $pattern, '/' )
            : $pattern;

        foreach ( explode( '|', $methods ) as $method ) {
            $this->befores[ $method ][] = [
                'fn' => $fn,
                'pattern' => $pattern
            ];
        }
    }

    /**
     * Store a route and a handling function to be executed when accessed using
     * one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function match( $methods, $pattern, $fn )
    {
        $pattern = $this->baseroute . '/' . trim( $pattern, '/' );
        $pattern = ( $this->baseroute )
            ? rtrim( $pattern, '/' )
            : $pattern;

        foreach ( explode( '|', $methods ) as $method ) {
            $this->routes[$method][] = [
                'fn' => $fn,
                'pattern' => $pattern
            ];
        }
    }

    /**
     * Shorthand for a route accessed using any method
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function all( $pattern, $fn )
    {
        $this->match( 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using GET
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function get( $pattern, $fn )
    {
        $this->match( 'GET', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using POST
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function post( $pattern, $fn )
    {
        $this->match( 'POST', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using PATCH
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function patch( $pattern, $fn )
    {
        $this->match( 'PATCH', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using DELETE
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function delete( $pattern, $fn )
    {
        $this->match( 'DELETE', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using PUT
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function put( $pattern, $fn )
    {
        $this->match( 'PUT', $pattern, $fn );
    }

    /**
     * Shorthand for a route accessed using OPTIONS
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object|callable $fn The handling function to be executed
     */
    public function options( $pattern, $fn )
    {
        $this->match( 'OPTIONS', $pattern, $fn );
    }

    /**
     * Mounts a collection of callables onto a base route
     *
     * @param string $baseroute Route subpattern to mount the callables on
     * @param callable $fn The callabled to be called
     */
    public function mount( $baseroute, $fn )
    {
        // Track current baseroute
        $curBaseroute = $this->baseroute;

        // Build new baseroute string
        $this->baseroute .= $baseroute;

        // Call the callable
        call_user_func( $fn );

        // Restore original baseroute
        $this->baseroute = $curBaseroute;
    }

    /**
     * Get all request headers
     * @return array The request headers
     */
    public function getRequestHeaders()
    {
        // getallheaders available, use that
        if ( function_exists( 'getallheaders' ) ) {
            return getallheaders();
        }

        // getallheaders not available: manually extract them
        $headers = [];

        foreach ( $_SERVER as $name => $value ) {
            if ( ( substr( $name, 0, 5) == 'HTTP_' )
                || ( $name == 'CONTENT_TYPE' )
                || ( $name == 'CONTENT_LENGTH' ) )
            {
                $key = str_replace(
                    [ ' ', 'Http' ],
                    [ '-', 'HTTP' ],
                    ucwords(
                        strtolower(
                            str_replace( '_', ' ', substr( $name, 5 ) ) )
                    ));
                $headers[ $key ] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get the request method used, taking overrides into account
     * @return string The Request method to handle
     */
    public function getRequestMethod()
    {
        // Take the method as found in $_SERVER
        $method = $_SERVER[ 'REQUEST_METHOD' ];

        // If it's a HEAD request override it to being GET and prevent
        // any output, as per HTTP Specification
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        if ( $_SERVER[ 'REQUEST_METHOD' ] === 'HEAD' ) {
            ob_start();
            $method = 'GET';
        }
        // If it's a POST request, check for a method override header
        elseif ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
            $headers = $this->getRequestHeaders();
            $headerSet = isset( $headers[ 'X-HTTP-Method-Override' ] );
            $methodExists = in_array(
                $headers[ 'X-HTTP-Method-Override' ],
                [ 'PUT', 'DELETE', 'PATCH' ] );

            if ( $headerSet && $methodExists )
            {
                $method = $headers[ 'X-HTTP-Method-Override' ];
            }
        }

        return $method;
    }

    /**
     * Execute the router: Loop all defined before middlewares and routes,
     * and execute the handling function if a match was found
     *
     * @param object|callable $callback Function to be executed after a matching
     *  route was handled (= after router middleware)
     * @return bool
     */
    public function run( $callback = NULL )
    {
        // Define which method we need to handle
        $this->method = $this->getRequestMethod();

        // Handle all before middlewares
        if ( isset( $this->befores[ $this->method ] ) ) {
            $this->handle( $this->befores[ $this->method ] );
        }

        // Handle all routes
        $numHandled = 0;

        if ( isset( $this->routes[ $this->method ] ) ) {
            $numHandled = $this->handle( $this->routes[ $this->method ], TRUE );
        }

        // If no route was handled, trigger the 404 (if any)
        if ( $numHandled === 0 ) {
            if ( $this->notFound && is_callable( $this->notFound ) ) {
                call_user_func( $this->notFound );
            }
            else {
                header( $_SERVER[ 'SERVER_PROTOCOL' ] . ' 404 Not Found' );
            }
        }
        // If a route was handled, perform the finish callback (if any)
        else {
            if ( $callback ) {
                $callback();
            }
        }

        // If it originally was a HEAD request, clean up after ourselves by
        // emptying the output buffer
        if ( $_SERVER[ 'REQUEST_METHOD' ] === 'HEAD' ) {
            ob_end_clean();
        }

        // Return true if a route was handled, false otherwise
        if ( $numHandled === 0 ) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Set the 404 handling function
     * @param object|callable $fn The function to be executed
     */
    public function set404( $fn )
    {
        $this->notFound = $fn;
    }

    /**
     * Handle a a set of routes: if a match is found, execute the relating
     * handling function
     *
     * @param array $routes Collection of route patterns and their handling
     *  functions
     * @param boolean $quitAfterRun Does the handle function need to quit after
     *  one route was matched?
     * @return int The number of routes handled
     */
    private function handle( $routes, $quitAfterRun = FALSE )
    {
        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // The current page URL
        $uri = $this->getCurrentUri();

        // Loop all routes
        foreach ( $routes as $route ) {
            $matched = preg_match_all(
                '#^' . $route[ 'pattern' ] . '$#',
                $uri,
                $matches,
                PREG_OFFSET_CAPTURE );

            // We have a match!
            if ( $matched ) {
                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice( $matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(
                    function ( $match, $index ) use ( $matches ) {
                        // We have a following parameter: take the substring from the
                        // current param position until the next one's position (thank
                        // you PREG_OFFSET_CAPTURE)
                        if ( isset( $matches[ $index + 1 ] )
                            && isset( $matches[ $index + 1 ][ 0 ] )
                            && is_array( $matches[ $index + 1 ][ 0 ] ) )
                        {
                            return trim(
                                substr(
                                    $match[ 0 ][ 0 ],
                                    0,
                                    $matches[ $index + 1 ][ 0 ][ 1 ] - $match[ 0 ][ 1 ] ),
                                '/' );
                        }
                        // We have no following parameters: return the whole lot
                        else {
                            return ( isset( $match[ 0 ][ 0 ] ) )
                                ? trim( $match[ 0 ][ 0 ], '/' )
                                : NULL;
                        }
                    },
                    $matches,
                    array_keys( $matches ) );

                // Call the handling function with the URL parameters
                call_user_func_array( $route[ 'fn' ], $params );

                // Yay!
                $numHandled++;

                // If we need to quit, then quit
                if ( $quitAfterRun ) {
                    break;
                }
            }
        }

        // Return the number of routes handled
        return $numHandled;
    }

    /**
     * Define the current relative URI
     * @return string
     */
    protected function getCurrentUri()
    {
        // Get the current Request URI and remove rewrite basepath from it
        // (= allows one to run the router in a subfolder)
        $basepath = implode(
            '/',
            array_slice(
                explode( '/', $_SERVER[ 'SCRIPT_NAME' ] ),
                0,
                -1
            )) . '/';
        $uri = substr( $_SERVER[ 'REQUEST_URI' ], strlen( $basepath ) );

        // Don't take query params into account on the URL
        if ( strstr( $uri, '?' ) ) {
            $uri = substr( $uri, 0, strpos( $uri, '?' ) );
        }

        // Remove trailing slash + enforce a slash at the start
        $uri = '/' . trim( $uri, '/' );

        return $uri;
    }
}