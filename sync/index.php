<?php

/**
 * Sync Engine
 *
 * This is the bootstrap file for the email syncing engine, called
 * from the CLI or managed via a supervisor. It works by checking
 * a list of saved IMAP credentials and runs through a flow of tasks.
 */

use voku\db\DB as DB
  , PhpImap\Mailbox as Mailbox
  , Pimple\Container as Container;

require( __DIR__ . '/vendor/autoload.php' );

// Set up dependency container and register all services
$container = new Container();
