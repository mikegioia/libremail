<?php

namespace App;

use Exception;
use App\Model\Account;
use App\Model\Message;
use App\Exceptions\ClientException;
use App\Exceptions\ServerException;
use App\Exceptions\NotFoundException;

// Autoload application and vendor libraries
require __DIR__.'/../vendor/autoload.php';

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Sessions last ~24 hours
ini_set('session.gc_maxlifetime', 86400);
// Tell PHP that we'll be outputting UTF-8 to the browser
mb_http_output('UTF-8');
// Tell PHP that we're using UTF-8 strings until the end
// of the script
mb_internal_encoding('UTF-8');

// Set up constants
define('GET', 'GET');
define('POST', 'POST');
define('INBOX', 'inbox');
define('SEARCH', 'search');
define('OUTBOX', 'outbox');
define('THREAD', 'thread');
define('VIEWEXT', '.phtml');
define('STARRED', 'starred');
define('MAILBOX', 'mailbox');
define('LIBREMAIL', 'LibreMail');
define('BASEDIR', __DIR__.'/..');
define('DIR', DIRECTORY_SEPARATOR);
define('VIEWDIR', BASEDIR.'/views');
define('DATE_DATABASE', 'Y-m-d H:i:s');
// Error constants
define('ERR_NO_TRASH_FOLDER', 1010);
define('ERR_NO_STARRED_FOLDER', 1011);
define('ERR_NO_SPAM_FOLDER', 1012);
define('ERR_TASK_ROLLBACK', 1020);
// Application preferences
define('PREF_THEME', 'wm.theme');

// Helper to load external config files
function getConfig(string $file)
{
    return include BASEDIR.'/config/'.$file.'.php';
}

// Load environment config
$config = parse_ini_file(BASEDIR.'/.env');

// Set the timezone now
date_default_timezone_set($config['TIMEZONE'] ?? View::DEFAULT_TZ);

// Set up the database connection
Model::initDb(
    sprintf(
        'mysql:host=%s:%s;dbname=%s;charset=%s',
        $config['DB_HOST'],
        $config['DB_PORT'],
        $config['DB_DATABASE'],
        $config['DB_CHARSET']),
    $config['DB_USERNAME'],
    $config['DB_PASSWORD'],
    $config['TIMEZONE']
);

// Pass the routes into the URL service
Url::setBase($config['WEB_URL']);

// Some classes utilize the config
View::setConfig($config);
Actions\Base::setConfig($config);

// Get the email address from the cookie (if set) and
// fetch the account. Otherwise, load the first active
// account in the database.
$email = $_COOKIE['email'] ?? null;
$account = $email
    ? (new Account)->getByEmail($email)
    : (new Account)->getFirstActive();

if (! $account) {
    throw new Exception('No account found!');
}

$router = new Router;
$controller = new Controller($account);

// Inbox
$router->get('/', [$controller, 'inbox']);
// Folder
$router->get('/folder/(\d+)', [$controller, 'folder']);
// Folder page
$router->get('/folder/(\d+)/(\d+)', [$controller, 'folderPage']);
// Starred messages in the inbox
$router->get('/starred/(\d+)', [$controller, 'starred']);
// Update messages
$router->post('/update', [$controller, 'update']);
// Update messages via GET but require a CSRF token
$router->get('/action', [$controller, 'action']);
// Undo an action or collection of actions
$router->post('/undo/(\d+)', [$controller, 'undo']);
// Get the star HTML for a message
$router->get('/star/(\w+)/(\w+)/(\d+)/(\w+).html', [$controller, 'getStar']);
// Set star flag on a message
$router->post('/star', [$controller, 'setStar']);
// Message thread
$router->get('/thread/(\d+)/(\d+)', [$controller, 'thread']);
// Original message
$router->get('/original/(\d+)', [$controller, 'original']);
// Account configuration
$router->get('/account', [$controller, 'account']);
// Updating account data
$router->post('/account', [$controller, 'updateAccount']);
// Settings and preferences
$router->get('/settings', [$controller, 'settings']);
// Updating settings
$router->post('/settings', [$controller, 'updateSettings']);
// Compose a new message
$router->get('/compose', [$controller, 'compose']);
// Edit an existing message
$router->get('/compose/(\d+)', [$controller, 'compose']);
// Send a new message
$router->post('/compose', [$controller, 'draft']);
// View the outbox messages
$router->get('/outbox', [$controller, 'outbox']);
// Delete draft
$router->post('/outbox/delete', [$controller, 'deleteDraft']);
// Preview a message
$router->get('/preview/(\d+)', [$controller, 'preview']);
// Send or queue an email
$router->post('/send', [$controller, 'send']);
// Close the JavaScript notification
$router->get('/closejsalert', [$controller, 'closeJsAlert']);
// Search messages
$router->get('/search', [$controller, 'search']);
// Handle 404s
$router->set404([$controller, 'error404']);

// Process route
try {
    $router->run();
} catch (NotFoundException $e) {
    header('HTTP/1.1 404 Not Found');
    echo '<h1>404 Page Not Found</h1>';
} catch (ClientException $e) {
    header('HTTP/1.1 400 Bad Request');
    echo '<h1>400 Bad Request</h1>';
    echo '<p>'.$e->getMessage().'</p>';
} catch (ServerException $e) {
    header('HTTP/1.1 500 Server Error');
    echo '<h1>500 Server Error</h1>';
    echo '<p>'.$e->getMessage().' [#'.$e->getCode().']</p>';
} catch (Exception $e) {
    if (true !== $config['DEBUG']) {
        header('HTTP/1.1 500 Server Error');
        echo '<h1>500 Server Error</h1>';
        echo '<p>'.$e->getMessage().' [#'.$e->getCode().']</p>';
    } else {
        throw $e;
    }
}
