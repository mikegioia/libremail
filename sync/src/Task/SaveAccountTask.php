<?php

namespace App\Task;

use App\Task
  , Exception
  , App\Command
  , App\Message
  , App\Diagnostics
  , App\Task\AbstractTask
  , App\Server\StatsServer
  , App\Message\AccountMessage
  , App\Message\NotificationMessage
  , App\Model\Account as AccountModel
  , App\Exceptions\Validation as ValidationException;

class SaveAccountTask extends AbstractTask
{
    public $host;
    public $port;
    public $email;
    public $password;
    protected $type = Task::SAVE_ACCOUNT;

    /**
     * Add or update the email account in the database.
     * @param StatsServer $server Optional server interface to
     *   broadcast messages to.
     */
    public function run( StatsServer $server = NULL )
    {
        $account = [
            'email' => $this->email,
            'imap_host' => $this->host,
            'imap_port' => $this->port,
            'password' => $this->password
        ];

        // Depending on the email hostname, try to infer the host
        // and port from our services config.
        $accountModel = new AccountModel( $account );
        $accountModel->loadServiceFromEmail();

        try {
            $accountModel->validate();
        }
        catch ( ValidationException $e ) {
            return $this->fail( $e->getMessage(), $server );
        }

        // Check if the connection works
        try {
            Diagnostics::testImapConnection( $accountModel->getData() );
        }
        catch ( Exception $e ) {
            return $this->fail(
                "There was a problem testing the IMAP connection: ".
                $e->getMessage() .".",
                $server );
        }

        // Save the account
        try {
            $accountModel->save();
        }
        catch ( Exception $e ) {
            return $this->fail( $e->getMessage(), $server );
        }

        Message::send(
            new NotificationMessage(
                STATUS_SUCCESS,
                "Your account has been added!" ),
            $server );
        Message::send(
            new AccountMessage( TRUE, $this->email ),
            $server );

        return TRUE;
    }

    private function fail( $message, $server )
    {
        Message::send(
            new NotificationMessage( STATUS_ERROR, $message ),
            $server );
        Message::send( new AccountMessage( FALSE ), $server );

        return FALSE;
    }
}