<?php

use App\Url;
use App\View;
use App\Model;
use App\Router;
use App\Folders;
use App\Messages;
use App\Model\Account;

// Autoload application and vendor libraries
require( __DIR__ .'/../vendor/autoload.php' );

// Turn on error reporting
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
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

// Helper to load config files
function getConfig( $file ) {
    return include( BASEDIR .'/config/'. $file .'.php' );
}

// Load environment config
$config = parse_ini_file( BASEDIR .'/.env' );

// Set up the database connection
Model::initDb(
    sprintf(
        "mysql:host=%s:%s;dbname=%s;charset=%s",
        $config[ 'DB_HOST' ],
        $config[ 'DB_PORT' ],
        $config[ 'DB_DATABASE' ],
        $config[ 'DB_CHARSET' ] ),
    $config[ 'DB_USERNAME' ],
    $config[ 'DB_PASSWORD' ] );

// Pass the routes into the URL service
Url::setBase( $config[ 'APP_URL' ] );

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

$router = new Router;

// Inbox
$router->get( '/', function () use ( $account ) {
    // Set up libraries
    $view = new View;
    $colors = getConfig( 'colors' );
    $folders = new Folders( $account, $colors );
    $messages = new Messages( $account, $folders );
    // Get the message data
    list( $starred, $messages, $counts ) = $messages->getThreads(
        $folders->getInboxId() );
    // Render the inbox
    $view->render( 'inbox', [
        'view' => $view,
        'counts' => $counts,
        'flagged' => $starred,
        'unflagged' => $messages,
        'folders' => $folders->get(),
        'folderTree' => $folders->getTree()
    ]);
});

// Update messages
$router->post( '/update', function () use ( $account ) {
    print_r( $_POST );
    exit;
    Url::redirect( '/' );
});

// Get the star HTML for a message
$router->get( '/star/(\d+)/(\w+).html', function ( $id, $state ) {
    header( 'Content-Type: text/html' );
    header( 'Cache-Control: max-age=86400' ); // one day
    (new View)->render( '/star', [
        'id' => $id,
        'flagged' => $state === 'on'
    ]);
});

// Set star flag on a message
$router->post( '/star', function () {
    // @todo update the flag
    (new View)->render( '/star', [
        'id' => Url::postParam( 'id', 0 ),
        'flagged' => Url::postParam( 'state', 'on' ) === 'on'
    ]);
});

$router->set404( function () {
    header( 'HTTP/1.1 404 Not Found' );
    echo '<h1>404 Page Not Found</h1>';
});

// Process route
$router->run();