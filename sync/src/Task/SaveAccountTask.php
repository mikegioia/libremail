<?php

namespace App\Task;

use App\Task;
use Exception;
use App\Message;
use App\Diagnostics;
use App\Server\StatsServer;
use App\Message\AccountMessage;
use App\Message\NotificationMessage;
use App\Model\Account as AccountModel;
use App\Exceptions\Validation as ValidationException;

class SaveAccountTask extends AbstractTask
{
    public $host;
    public $port;
    public $name;
    public $email;
    public $password;

    protected $type = Task::SAVE_ACCOUNT;

    /**
     * Add or update the email account in the database.
     *
     * @param StatsServer $server optional server interface to
     *   broadcast messages to
     */
    public function run(StatsServer $server = null)
    {
        $account = [
            'is_active' => 1,
            'name' => $this->name,
            'email' => $this->email,
            'imap_host' => $this->host,
            'imap_port' => $this->port,
            'password' => $this->password
        ];

        // Depending on the email hostname, try to infer the host
        // and port from our services config.
        $accountModel = new AccountModel($account);
        $accountModel->loadServiceFromEmail();

        try {
            $accountModel->validate();
        } catch (ValidationException $e) {
            return $this->fail($e->getMessage(), $server);
        }

        // Check if the connection works
        try {
            Diagnostics::testImapConnection($accountModel->getData());
        } catch (Exception $e) {
            return $this->fail(
                'There was a problem testing the IMAP connection: '.
                $e->getMessage().'.',
                $server
            );
        }

        // Save the account
        try {
            $accountModel->save([], true);
        } catch (Exception $e) {
            return $this->fail($e->getMessage(), $server);
        }

        Message::send(
            new NotificationMessage(
                STATUS_SUCCESS,
                'Your account has been saved!'),
            $server);
        Message::send(
            new AccountMessage(true, $this->email),
            $server);

        return true;
    }

    private function fail($message, $server)
    {
        Message::send(
            new NotificationMessage(STATUS_ERROR, $message),
            $server);
        Message::send(
            new AccountMessage(false, $this->email),
            $server);

        return false;
    }
}
