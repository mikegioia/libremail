<?php

namespace App\Task;

use App\Task
  , Exception
  , App\Message
  , App\Diagnostics
  , App\Task\AbstractTask
  , App\Message\AccountMessage
  , App\Message\NotificationMessage;

class SaveAccountTask extends AbstractTask
{
    public $host;
    public $port;
    public $email;
    public $password;
    protected $type = Task::SAVE_ACCOUNT;

    /**
     * Add or update the email account in the database.
     */
    public function run()
    {
        // Lock the account page
        Message::send( new AccountMessage( TRUE ) );

        // Check if the connection works
        try {
            Diagnostics::testImapConnection([
                'email' => $this->email,
                'imap_host' => $this->host,
                'imap_port' => $this->port,
                'password' => $this->password
            ]);
        }
        catch ( Exception $e ) {
            Message::send(
                new NotificationMessage(
                    STATUS_ERROR,
                    "There was a problem testing the IMAP connection: ".
                    $e->getMessage()
                ));
            goto unlockAccount;
        }

        // Save the account
        //$accountModel = new AccountModel( $newAccount );
        //$accountModel->save();

        unlockAccount: {
            Message::send( new AccountMessage( FALSE ) );
        }
    }
}