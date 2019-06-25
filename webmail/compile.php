<?php

/**
 * Compiles asset files into a single file.
 */
$cwd = __DIR__;
$css = [
    // Vendor files
    'font-awesome.css',
    'open-sans.css',
    'normalize.css',
    'skeleton.css',
    // App files
    'app/base.css',
    'app/nav.css',
    'app/actions.css',
    'app/buttons.css',
    'app/tooltips.css',
    'app/alerts.css',
    'app/dialogs.css',
    'app/notifications.css',
    'app/dropdowns.css',
    'app/messages.css',
    'app/threads.css',
    'app/compose.css',
    'app/settings.css',
    'app/media.css',
    // Themes
    'themes/warmlight.css',
    'themes/coolnight.css'
];

// Open the file, set pointer to start and truncate
$fp = fopen("$cwd/www/build/libremail.css", 'w+');

foreach ($css as $file) {
    $contents = file_get_contents("$cwd/www/css/$file");
    fwrite($fp, $contents.PHP_EOL);
}

fclose($fp);

echo 'File written to build/libremail.css', PHP_EOL;
