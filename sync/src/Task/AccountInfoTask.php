<?php

namespace App\Task;

use App\Task;
use App\Message;
use App\Server\StatsServer;
use App\Message\AccountInfoMessage;
use App\Message\NotificationMessage;
use App\Model\Account as AccountModel;

class AccountInfoTask extends AbstractTask
{
    public $email;

    protected $type = Task::ACCOUNT_INFO;

    /**
     * Returns info about the account.
     *
     * @param StatsServer $server optional server interface to
     *   broadcast messages to
     */
    public function run(StatsServer $server = null)
    {
        $account = (new AccountModel)->getByEmail($this->email);

        if (! $account) {
            Message::send(
                new NotificationMessage(
                    STATUS_ERROR,
                    'That account could not be found.'),
                $server
            );

            return false;
        }

        Message::send(new AccountInfoMessage($account), $server);

        return true;
    }
}
