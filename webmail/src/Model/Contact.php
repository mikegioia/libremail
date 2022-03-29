<?php

namespace App\Model;

use App\Model;
use PDO;

class Contact extends Model
{
    public $id;
    public $name;
    public $tally;
    public $account_id;
    public $created_at;

    private static $cache;

    /**
     * Get all of the contacts for an account.
     *
     * @return array
     */
    public static function getByAccount(int $accountId)
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        self::$cache = self::getDb()
            ->select()
            ->from('contacts')
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());

        return self::$cache;
    }
}
