<?php

namespace App\Traits;

trait ConfigTrait
{
    private static $config;
    private static $timezone;

    public static function setConfig(array $config)
    {
        self::$config = $config;
        self::setTimezone($config['TIMEZONE'] ?? self::DEFAULT_TZ);
    }

    public static function setTimezone(string $timezone)
    {
        self::$timezone = $timezone;
    }

    /**
     * Class method to load an environment variable.
     */
    public function env(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }
}
