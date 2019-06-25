<?php

namespace App;

use Fn;
use Exception;
use App\Task\SaveAccountTask;
use App\Task\AccountInfoTask;
use App\Task\RemoveAccountTask;

class Task
{
    const SAVE_ACCOUNT = 'save_account';
    const ACCOUNT_INFO = 'account_info';
    const REMOVE_ACCOUNT = 'remove_account';

    /**
     * Takes in a type and a data array and makes a new Task.
     *
     * @param string $type
     * @param array $data
     *
     * @throws Exception
     *
     * @return AbstractTask
     */
    public static function make(string $type, array $data)
    {
        switch ($type) {
            case self::SAVE_ACCOUNT:
                Fn\expects($data)->toHave(['email', 'password']);

                return new SaveAccountTask($data);

            case self::ACCOUNT_INFO:
                Fn\expects($data)->toHave(['email']);

                return new AccountInfoTask($data);

            case self::REMOVE_ACCOUNT:
                Fn\expects($data)->toHave(['email']);

                return new RemoveAccountTask($data);
        }

        throw new Exception(
            'Invalid task type passed to Task::make'
        );
    }
}
