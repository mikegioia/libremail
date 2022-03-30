<?php

/**
 * ================================================================
 * DO NOT EDIT THIS FILE
 *
 * This contains the system constants and should not be edited.
 * ================================================================
 */

// General
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
define('BUILDDIR', BASEDIR.'/www/build');
define('FONTSDIR', BASEDIR.'/www/fonts');

// Dates
define('DATE_DATETIME', 'j F Y H:i');
define('DATE_DATABASE', 'Y-m-d H:i:s');
define('DATE_CALENDAR_SHORT', 'j M');
define('DATE_CALENDAR', 'j/m/Y');
define('DATE_TIME_SHORT', 'H:i');

// Error constants
define('ERR_NO_TRASH_FOLDER', 1010);
define('ERR_NO_STARRED_FOLDER', 1011);
define('ERR_NO_SPAM_FOLDER', 1012);
define('ERR_TASK_ROLLBACK', 1020);

// Server optionns
define('SERVER_PHP', 'php');

// Application preferences
define('PREF_THEME', 'wm.theme');
