<?php

// Autoload application and vendor libraries
require( __DIR__ .'/../vendor/autoload.php' );

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