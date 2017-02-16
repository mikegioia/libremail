<?php

namespace App;

use Fn
  , Exception
  , App\Task\SaveAccountTask
  , App\Task\AccountInfoTask;

class Task
{
    const SAVE_ACCOUNT = 'save_account';
    const ACCOUNT_INFO = 'account_info';

    /**
     * Takes in a type and a data array and makes a new Task.
     * @param String $type
     * @param Object $data
     * @throws Exception
     * @return AbstractTask
     */
    static public function make( $type, $data )
    {
        switch ( $type ) {
            case self::SAVE_ACCOUNT:
                Fn\expects( $data )->toHave([ 'email', 'password' ]);
                return new SaveAccountTask( $data );
            case self::ACCOUNT_INFO:
                Fn\expects( $data )->toHave([ 'email' ]);
                return new AccountInfoTask( $data );
        }

        throw new Exception(
            "Invalid task type passed to Task::make" );
    }
}