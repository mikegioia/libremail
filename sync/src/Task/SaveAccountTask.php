<?php

namespace App\Task;

use App\Task
  , Exception
  , App\Diagnostics
  , App\Task\AbstractTask;

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
            echo $e->getMessage(), "\n";
            return;
        }

        // Save the account
        $accountModel = new AccountModel( $newAccount );
        $accountModel->save();
    }
}