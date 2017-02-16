<?php

namespace App\Task;

use App\Task
  , App\Message
  , App\Task\AbstractTask
  , App\Server\StatsServer
  , App\Message\AccountInfoMessage
  , App\Message\NotificationMessage
  , App\Model\Account as AccountModel;

class AccountInfoTask extends AbstractTask
{
    public $email;
    protected $type = Task::ACCOUNT_INFO;

    /**
     * Returns info about the account.
     * @param StatsServer $server Optional server interface to
     *   broadcast messages to.
     */
    public function run( StatsServer $server = NULL )
    {
        $account = (new AccountModel)->getByEmail( $this->email );

        if ( ! $account ) {
            Message::send(
                new NotificationMessage(
                    STATUS_ERROR,
                    "That account could not be found." ),
                $server );

            return FALSE;
        }

        Message::send( new AccountInfoMessage( $account ), $server );

        return TRUE;
    }
}