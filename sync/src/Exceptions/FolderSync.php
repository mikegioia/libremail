<?php

namespace App\Exceptions;

class FolderSync extends \Exception
{
    public $code = 3002;
    public $message = "Failed to sync IMAP folders.";
}