<?php

// Autoload application and vendor libraries
require( __DIR__ .'/../vendor/autoload.php' );

// Load constants and configuration

// Set up route callbacks and trigger routing
$router = new \App\Router();

$router->get( '/', function () {
    echo "Hello world";
});

$router->match( 'GET', '/about', function () {
    echo 'about';
});

$router->run();