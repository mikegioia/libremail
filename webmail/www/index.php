<?php

// Autoload application and vendor libraries
require( __DIR__ .'/../vendor/autoload.php' );

// Tell PHP that we'll be outputting UTF-8 to the browser
mb_http_output( 'UTF-8' );
// Tell PHP that we're using UTF-8 strings until the end
// of the script
mb_internal_encoding( 'UTF-8' );

// Load constants and configuration

// Set up route callbacks and trigger routing
$router = new \App\Router();

$router->get( '/', function () {
    echo file_get_contents( __DIR__ .'/demo.html' );
});

$router->post( '/', function () {
    echo file_get_contents( __DIR__ .'/demo.html' );
});

$router->match( 'GET', '/about', function () {
    echo 'about';
});

$router->run();