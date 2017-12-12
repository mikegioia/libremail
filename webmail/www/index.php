<?php

use App\Url;
use App\View;
use App\Model;
use App\Router;
use App\Folders;
use App\Actions;
use App\Messages;
use App\Model\Account;
use App\Model\Message;
use App\Exceptions\ClientException;

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
define( 'INBOX', 'inbox' );
define( 'VIEWEXT', '.phtml' );
define( 'STARRED', 'starred' );
define( 'BASEDIR', __DIR__ .'/..' );
define( 'DIR', DIRECTORY_SEPARATOR );
define( 'VIEWDIR', BASEDIR .'/views' );
define( 'DATE_DATABASE', 'Y-m-d h:i:s' );

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

// Helper function to render a mailbox
$renderMailbox = function ( $id, $page = 1, $limit = 25 ) use ( $account ) {
    // Set up libraries
    $view = new View;
    $colors = getConfig( 'colors' );
    $select = Url::getParam( 'select' );
    $folders = new Folders( $account, $colors );
    $messages = new Messages( $account, $folders );
    $folderId = ( $id === INBOX || $id === STARRED )
        ? $folders->getInboxId()
        : $id;
    $folder = $folders->getById( $folderId );

    if ( ! $folder ) {
        throw new ClientException( "Folder #$id not found!" );
    }

    // Get the message data
    list( $flagged, $unflagged, $counts ) = $messages->getThreads(
        $folderId,
        $page,
        $limit, [
            Message::SPLIT_FLAGGED => $id === INBOX,
            Message::ONLY_FLAGGED => $id === STARRED
        ]);

    // Render the inbox
    $view->render( 'mailbox', [
        'urlId' => $id,
        'view' => $view,
        'page' => $page,
        'counts' => $counts,
        'select' => $select,
        'flagged' => $flagged,
        'folders' => $folders,
        'folderId' => $folderId,
        'unflagged' => $unflagged,
        'showPaging' => $id !== INBOX,
        'mainHeading' => ( $id === INBOX )
            ? 'Everything else'
            : $folder->name
    ]);
};

// Inbox
$router->get( '/', function () use ( $renderMailbox ) {
    $renderMailbox( INBOX );
});

// Folder
$router->get( '/folder/(\d+)', function ( $id ) use ( $renderMailbox ) {
    $renderMailbox( $id, 1, 5 );
});

// Starred messages in the inbox
$router->get( '/starred/(\d+)', function ( $page ) use ( $renderMailbox ) {
    $renderMailbox( STARRED, $page );
});

// Folder page
$router->get( '/folder/(\d+)/(\d+)', function ( $id, $page ) use ( $renderMailbox ) {
    $renderMailbox( $id, $page, 5 );
});

// Update messages
$router->post( '/update', function () use ( $account ) {
    $colors = getConfig( 'colors' );
    $folders = new Folders( $account, $colors );
    $actions = new Actions( $folders, $_POST + $_GET );
    $actions->run();
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
$router->post( '/star', function () use ( $account ) {
    $folders = new Folders( $account, [] );
    $actions = new Actions( $folders, $_POST + $_GET );
    $actions->handleAction(
        ( Url::postParam( 'state', 'on' ) === 'on' )
            ? Actions::FLAG
            : Actions::UNFLAG,
        [
            Url::postParam( 'id', 0 )
        ],
        [] );
    (new View)->render( '/star', [
        'id' => Url::postParam( 'id', 0 ),
        'flagged' => Url::postParam( 'state', 'on' ) === 'on'
    ]);
});

// Handle 404s
$router->set404( function () {
    header( 'HTTP/1.1 404 Not Found' );
    echo '<h1>404 Page Not Found</h1>';
});

// Process route
try {
    $router->run();
}
catch ( ClientException $e ) {
    header( 'HTTP/1.1 400 Bad Request' );
    echo '<h1>400 Bad Request</h1>';
    echo '<p>'. $e->getMessage() .'</p>';
}
// catch ( Exception $e ) {
//     header( 'HTTP/1.1 500 Server Error' );
//     echo '<h1>500 Server Error</h1>';
//     echo '<p>'. $e->getMessage() .'</p>';
// }