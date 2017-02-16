<?php

namespace App\Task;

use App\Task
  , App\Message
  , App\Command
  , App\Task\AbstractTask
  , App\Server\StatsServer
  , App\Message\AccountInfoMessage
  , App\Message\NotificationMessage
  , App\Model\Account as AccountModel;

class RemoveAccountTask extends AbstractTask
{
    public $email;
    protected $type = Task::ACCOUNT_INFO;

    /**
     * Marks the account as inactive.
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

        // Save the account
        try {
            $account->save([
                'is_active' => 0
            ], TRUE );
        }
        catch ( Exception $e ) {
            Message::send(
                new NotificationMessage( STATUS_ERROR, $e->getMessage() ),
                $server );

            return FALSE;
        }

        Command::send( Command::make( Command::STOP ) );
        Message::send( new AccountInfoMessage( $account ), $server );

        return TRUE;
    }
}