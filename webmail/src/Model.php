<?php

namespace App;

use DateTime;
use DateTimeZone;
use Pb\PDO\Database;

class Model
{
    // Underscore these to not conflict with class variables
    // Especially during setData(), this can cause problems
    private static $_db;
    private static $_dsn;
    private static $_username;
    private static $_password;
    private static $_timezone;

    public const ASC = 'asc';
    public const DESC = 'desc';

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

    public function setData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Store the connection info statically.
     */
    public static function initDb(
        string $dsn,
        string $username,
        string $password,
        string $timezone
    ) {
        self::$_dsn = $dsn;
        self::$_username = $username;
        self::$_password = $password;
        self::$_timezone = $timezone;
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
        if (isset(self::$_db)) {
            return self::$_db;
        }

        self::$_db = new Database(
            self::$_dsn,
            self::$_username,
            self::$_password
        );

        return self::$_db;
    }

    /**
     * Returns a new local DateTime object.
     *
     * @return DateTime
     */
    public function localDate(string $localDate = null)
    {
        return new DateTime(
            is_null($localDate)
                ? date(DATE_DATABASE)
                : $localDate,
            new DateTimeZone(self::$_timezone)
        );
    }

    /**
     * Returns a new UTC-adjusted DateTime object.
     *
     * @return DateTime
     */
    public function utcDate(string $utcDate = null)
    {
        return new DateTime(
            is_null($utcDate)
                ? gmdate(DATE_DATABASE)
                : $utcDate,
            new DateTimeZone('UTC')
        );
    }

    /**
     * Takes in a UTC date string and converts it to the local time.
     *
     * @return DateTime
     */
    public function utcToLocal(string $utcDate = null)
    {
        $datetime = $this->utcDate($utcDate);
        $datetime->setTimezone(new DateTimeZone(self::$_timezone));

        return $datetime;
    }

    /**
     * Determines if the model object has a valid ID.
     *
     * @return bool
     */
    public function exists()
    {
        return is_numeric($this->id)
            && 0 !== (int) $this->id;
    }
}
