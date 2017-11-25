<?php

use App\View;
use App\Model;
use App\Router;
use App\Folders;
use App\Messages;
use App\Model\Account;
use Slim\PDO\Database;

// Autoload application and vendor libraries
require( __DIR__ .'/../vendor/autoload.php' );

// Tell PHP that we'll be outputting UTF-8 to the browser
mb_http_output( 'UTF-8' );
// Tell PHP that we're using UTF-8 strings until the end
// of the script
mb_internal_encoding( 'UTF-8' );

// Set up constants
define( 'GET', 'GET' );
define( 'POST', 'POST' );
define( 'VIEWEXT', '.phtml' );
define( 'BASEDIR', __DIR__ .'/..' );
define( 'DIR', DIRECTORY_SEPARATOR );
define( 'VIEWDIR', BASEDIR .'/views' );

// Load environment config
$config = parse_ini_file( BASEDIR .'/.env' );

// Set up the database connection
Model::setDb(
    new Database(
        sprintf(
            "mysql:host=%s:%s;dbname=%s;charset=%s",
            $config[ 'DB_HOST' ],
            $config[ 'DB_PORT' ],
            $config[ 'DB_DATABASE' ],
            $config[ 'DB_CHARSET' ] ),
        $config[ 'DB_USERNAME' ],
        $config[ 'DB_PASSWORD' ]
    ));

// Get the email address from the cookie (if set) and
// fetch the account. Otherwise, load the first active
// account in the database.
$email = ( isset( $_COOKIE[ 'email' ] ) )
    ? $_COOKIE[ 'email' ]
    : NULL;
$account = ( $email )
    ? (new Account)->getByEmail( $email )
    : (new Account)->getFirstActive();

if ( ! $account ) {
    throw new \Exception( "No account found!" );
}

// Set up libraries
$router = new Router;
$folders = new Folders( $account );
$messages = new Messages( $account );

// Set up routes
$router->get( '/', function () use ( $folders, $messages ) {
    list( $starred, $messages ) = $messages->getThreads(
        $folders->getInboxId() );
    echo (new View)->render( 'inbox', [
        'starred' => $starred,
        'messages' => $messages,
        'folders' => $folders->get(),
        'folderTree' => $folders->getTree()
    ]);
});

$router->get( '/demo', function () {
    echo file_get_contents( __DIR__ .'/demo.html' );
});

$router->post( '/', function () {
    echo file_get_contents( BASEDIR .'/demo.html' );
});

$router->match( GET, '/about', function () {
    echo 'about';
});

// Process route
$router->run();