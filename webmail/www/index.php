<?php

namespace App;

use App\Actions\Base as BaseAction;
use App\Exceptions\ClientException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ServerException;
use App\Model\Account;
use App\Model\Message;
use Exception;

// Autoload application and vendor libraries
require __DIR__.'/../vendor/autoload.php';

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Sessions last ~24 hours
ini_set('session.gc_maxlifetime', '86400');
// Tell PHP that we'll be outputting UTF-8 to the browser
mb_http_output('UTF-8');
// Tell PHP that we're using UTF-8 strings until the end
// of the script
mb_internal_encoding('UTF-8');

// Load up constants
require __DIR__.'/../config/constants.php';

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
        $config['DB_CHARSET']
    ),
    $config['DB_USERNAME'],
    $config['DB_PASSWORD'],
    $config['TIMEZONE']
);

// Pass the routes into the URL service
Url::setBase($config['WEB_URL']);

// Some classes utilize the config
View::setConfig($config);
Router::setConfig($config);
BaseAction::setConfig($config);

// Get the email address from the cookie (if set) and
// fetch the account. Otherwise, load the first active
// account in the database.
$email = $_COOKIE['email'] ?? null;
$account = $email
    ? (new Account())->getByEmail($email)
    : (new Account())->getFirstActive();
$account = $account ?: new Account();

$router = new Router();
$controller = new Controller($account);

// If there's no account, the only allowed pages are the
// account creation page, and the account create endpoint.
if (! $account->exists()) {
    // Create account
    $router->get('/', [$controller, 'setup']);
    // Save account
    $router->post('/account/create', [$controller, 'createAccount']);
} elseif (! $account->hasFolders()) {
    // Error page
    $router->get('/', [$controller, 'errorNoFolders']);
    // Account configuration
    $router->get('/account', [$controller, 'account']);
    // Updating account data
    $router->post('/account', [$controller, 'updateAccount']);
    // Settings and preferences
    $router->get('/settings', [$controller, 'settings']);
    // Updating settings
    $router->post('/settings', [$controller, 'updateSettings']);
} else {
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
    // Reply to a message
    $router->get('/reply/(\d+)', [$controller, 'reply']);
    // Reply-all to a message
    $router->get('/replyall/(\d+)', [$controller, 'replyAll']);
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
}

// Handle public asset files
$router->get('/build/(\w+).css', [$controller, 'stylesheet']);
$router->get('/fonts/([\w\-]+).(\w+)', [$controller, 'font']);

// Handle 404s
$router->set404([$controller, 'error404']);

// Process route
try {
    $router->run();
} catch (NotFoundException $e) {
    View::show404();
} catch (ClientException $e) {
    View::showError(View::HTTP_400, 'Bad Request', $e->getMessage());
} catch (ServerException $e) {
    $message = $e->getMessage().' [#'.$e->getCode().']';
    View::showError(View::HTTP_500, 'Server Error', $message);
} catch (Exception $e) {
    if (1 !== (int) $config['DEBUG']) {
        $message = $e->getMessage().' [#'.$e->getCode().']';
        View::showError(View::HTTP_500, 'Server Error', $message);
    } else {
        throw $e;
    }
}
