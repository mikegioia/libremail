<?php

/**
 * Sync Engine
 *
 * This is the bootstrap file for the email syncing engine, called
 * from the CLI or managed via a supervisor. It works by checking
 * a list of saved IMAP credentials and runs through a flow of tasks.
 */

use App\Log as Log
  , voku\db\DB as DB
  , App\Console as Console
  , Pimple\Container as Container;

require( __DIR__ . '/vendor/autoload.php' );

// Process command line arguments

// Load configuration files
$default = parse_ini_file( __DIR__ .'/config/default.ini', TRUE );
$local = parse_ini_file( __DIR__ .'/config/local.ini', TRUE );
$config = array_replace_recursive( $default, $local );

// Set up dependency container and register all services
$di = new Container();

// Store the configuration as a service
$di[ 'config' ] = $config;

// Console/CLI service
$di[ 'console' ] = function ( $c ) {
    return new Console();
};
$di[ 'cli' ] = function ( $c ) {
    return $c[ 'console' ]->getCLI();
};

// MySQLi service, this uses Voku's library
$di[ 'db' ] = function ( $c ) {
    $dbConfig = $c[ 'config' ][ 'sql' ];
    return DB::getInstance(
        $dbConfig[ 'hostname' ],
        $dbConfig[ 'username' ],
        $dbConfig[ 'password' ],
        $dbConfig[ 'database' ],
        $dbConfig[ 'port' ],
        $dbConfig[ 'charset' ],
        $exitOnError = FALSE,
        $echoOnError = FALSE );
};

// Logging service
$di[ 'log' ] = function ( $c ) {
    $stdout = ( $c[ 'console' ]->interactive === TRUE );
    $log = new Log( $c[ 'config' ][ 'log' ], $stdout );
    return $log->getLogger();
};

// Run initialization checks, like if the database exists or if there
// are email accounts saved. This may prompt the user to add an account
// if we're running in interactive mode.
try {
    $startup = new \App\Startup( $di[ 'config' ], $di[ 'console' ] );
    $startup->run( $di );
}
catch ( \Exception $e ) {
    if ( $di[ 'console' ]->interactive === TRUE ) {
        $di[ 'cli' ]->bold()->backgroundRed()->white( $e->getMessage() );
        $di[ 'cli' ]->br();

        if ( $config[ 'app' ][ 'stacktrace' ] ) {
            $di[ 'cli' ]->comment( $e->getTraceAsString() );
            $di[ 'cli' ]->br();
        }
    }
    else {
        $di[ 'log' ]->addError(
            $e->getMessage() . PHP_EOL . $e->getTraceAsString() );
    }
}