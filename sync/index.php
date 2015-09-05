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
  , Pimple\Container as Container
  , League\CLImate\CLImate as CLI;

require( __DIR__ . '/vendor/autoload.php' );

// Process command line arguments

// Load configuration files
$default = parse_ini_file( __DIR__ .'/config/default.ini', TRUE );
$local = parse_ini_file( __DIR__ .'/config/local.ini', TRUE );
$config = array_replace_recursive( $default, $local );

// Set up dependency container and register all services
$container = new Container();

// Store the configuration as a service
$container[ 'config' ] = $config;

// MySQLi service, this uses Voku's library
$container[ 'db' ] = function ( $c ) {
    $dbConfig = $c[ 'config' ][ 'sql' ];

    return DB::getInstance(
        $dbConfig[ 'hostname' ],
        $dbConfig[ 'username' ],
        $dbConfig[ 'password' ],
        $dbConfig[ 'database' ] );
};

// Logging service
$container[ 'log' ] = function ( $c ) {
    $log = new Log( $c[ 'config' ][ 'log' ], TRUE );
    return $log->getLogger();
};

// Console/CLI service
$container[ 'cli' ] = function ( $c ) {
    return new CLI();
};

$container[ 'log' ]->addError( 'Bar' );

$container[ 'cli' ]->out('This prints to the terminal.');
$container[ 'cli' ]->whisper('ssshhhhhh.');

// Run initialization checks, like if the databaes exists or if there
// are email accounts saved. This may prompt the user to add an account
// if we're running in interactive mode.

