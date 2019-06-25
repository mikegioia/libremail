<?php

namespace App\Task;

use App\Task;
use App\Message;
use App\Command;
use App\Server\StatsServer;
use App\Message\AccountInfoMessage;
use App\Message\NotificationMessage;
use App\Model\Account as AccountModel;

class RemoveAccountTask extends AbstractTask
{
    public $email;
    protected $type = Task::ACCOUNT_INFO;

    /**
     * Marks the account as inactive.
     *
     * @param StatsServer $server optional server interface to
     *   broadcast messages to
     *
     * @return bool
     */
    public function run(StatsServer $server = null)
    {
        $account = (new AccountModel)->getByEmail($this->email);

        if (! $account) {
            Message::send(
                new NotificationMessage(
                    STATUS_ERROR,
                    'That account could not be found.'),
                $server);

            return false;
        }

        // Save the account
        try {
            $account->save([
                'is_active' => 0
            ], true);
        } catch (Exception $e) {
            Message::send(
                new NotificationMessage(STATUS_ERROR, $e->getMessage()),
                $server);

            return false;
        }

        Command::send(Command::make(Command::STOP));
        Message::send(new AccountInfoMessage($account), $server);

        return true;
    }
}
