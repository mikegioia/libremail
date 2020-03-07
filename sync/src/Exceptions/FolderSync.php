<?php

namespace App\Exceptions;

use Exception;

class FolderSync extends Exception
{
    public $code = EXC_FOLDER_SYNC;
    public $message = 'Failed to sync IMAP folders';
}
