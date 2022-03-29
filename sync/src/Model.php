<?php

namespace App;

use DateTime;
use DateTimeZone;
use League\CLImate\CLImate;
use Monolog\Logger;
use Particle\Validator\ValidationResult;
use Pb\PDO\Database;
use Pimple\Container;

class Model
{
    protected static $db;
    protected static $di;
    protected static $cli;
    protected static $log;
    protected static $config;

    public const ASC = 'asc';
    public const DESC = 'desc';

    public const UTC = 'UTC';

    /**
     * @var bool Mode for returning new DB instance
     */
    protected static $factoryMode = false;

    /**
     * @var Database|null Local reference if in factory mode
     */
    protected static $localDb;

    /**
     * Data could be an integer or an array. For integers,
     * it will set the ID to that value. For arrays, it will
     * update the internal properties from the array.
     *
     * @param array|int $data
     */
    public function __construct($data = null)
    {
        if (! $data) {
            return;
        }

        $this->setData($data);
    }

    /**
     * Implemented in model.
     */
    public function getData()
    {
        return [];
    }

    /**
     * @param array|int $data
     */
    public function setData($data)
    {
        if (is_scalar($data)) {
            if (property_exists($this, 'id')) {
                $this->id = $data;
            }
        } else {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Sets the internal database connection statically for all
     * models to use.
     */
    public static function setDb(Database $db)
    {
        self::$db = $db;
    }

    public static function setCLI(CLImate $cli)
    {
        self::$cli = $cli;
    }

    public static function setLog(Logger $log)
    {
        self::$log = $log;
    }

    public static function setConfig(array $config)
    {
        self::$config = $config;
    }

    public static function setDbFactory(Container $di)
    {
        self::$di = $di;
        self::$factoryMode = true;
    }

    public function db()
    {
        return self::getDb();
    }

    public static function getDb()
    {
        if (true === self::$factoryMode) {
            if (isset(self::$localDb)) {
                return self::$localDb;
            }

            self::$localDb = self::$di['db_factory'];

            return self::$localDb;
        }

        return self::$db;
    }

    public function ping()
    {
        $this->db()->query('SELECT 1;');
    }

    public function cli()
    {
        return self::$cli;
    }

    public function log()
    {
        return self::$log;
    }

    /**
     * If a key came in, return the config value. The key is of
     * the form some.thing and will be exploded on the period.
     *
     * @param string $key Optional key to lookup
     *
     * @return mixed|array
     */
    public function config(string $key = '')
    {
        if (! $key) {
            return self::$config;
        }

        $lookup = self::$config;

        foreach (explode('.', $key) as $part) {
            if (! isset($lookup[$part])) {
                return null;
            }

            $lookup = $lookup[$part];
        }

        return $lookup;
    }

    public function getError()
    {
        return $this->db()->getError();
    }

    public function getErrorString(ValidationResult $result, string $message)
    {
        $return = [];
        $messages = $result->getMessages();

        foreach ($messages as $key => $messages) {
            $return = array_merge($return, array_values($messages));
        }

        return trim(
            sprintf("%s\n\n%s", $message, implode("\n", $return)),
            ". \t\n\r\0\x0B"
        ).'.';
    }

    /**
     * Returns a new local DateTime object.
     *
     * @return DateTime
     */
    public function localDate(string $localDate = null)
    {
        $timezone = $this->config('app.timezone');

        return new DateTime(
            is_null($localDate)
                ? date(DATE_DATABASE)
                : $localDate,
            new DateTimeZone($timezone)
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
            new DateTimeZone(self::UTC)
        );
    }
}
