<?php

namespace App;

use Pb\PDO\Database;

class Model
{
    private static $db;
    private static $dsn;
    private static $username;
    private static $password;

    const ASC = 'asc';
    const DESC = 'desc';

    /**
     * @param array|Model|null $data
     */
    public function __construct($data = null)
    {
        if (! $data) {
            return;
        }

        if (is_scalar($data)) {
            $this->id = $data;
        } else {
            $this->setData($data);
        }
    }

    public function setData($data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Store the connection info statically.
     */
    public static function initDb(string $dsn, string $username, string $password)
    {
        self::$dsn = $dsn;
        self::$username = $username;
        self::$password = $password;
    }

    /**
     * Sets the internal database connection statically for all
     * models to use. The database connection is only loaded the
     * first time it's referenced.
     *
     * @return Database
     */
    public function db()
    {
        return self::getDb();
    }

    public static function getDb()
    {
        if (isset(self::$db)) {
            return self::$db;
        }

        self::$db = new Database(
            self::$dsn,
            self::$username,
            self::$password);

        return self::$db;
    }
}
