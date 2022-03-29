<?php

namespace App\Model;

use App\Model;
use PDO;
use stdClass;

class Meta extends Model
{
    public $key;
    public $value;
    public $updated_at;

    private static $cache;

    public const ALLOWED = [
        PREF_THEME
    ];

    /**
     * Get the value for a specific key. If none exists,
     * fallback to the default param.
     *
     * @param mixed $default
     * @param stdClass $source
     *
     * @return mixed
     */
    public static function get(string $key, $default = null, stdClass $source = null)
    {
        $setting = null;
        $source = $source ?: self::$cache;

        if (null === $source) {
            $setting = self::getDb()
                ->select()
                ->from('meta')
                ->where('key', '=', $key)
                ->execute()
                ->fetchObject();
        } else {
            foreach ($source as $cacheKey => $cacheValue) {
                if ($cacheKey === $key) {
                    return $cacheValue;
                }
            }
        }

        if (! $setting) {
            return $default;
        }

        return $setting->value;
    }

    /**
     * Get the entire set of metadata records transformed
     * into key/value pairs.
     *
     * @return object
     */
    public static function getAll()
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        $list = new stdClass;
        $items = self::getDb()
            ->select()
            ->from('meta')
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());

        foreach ($items as $item) {
            $list->{$item->key} = $item->value;
        }

        self::$cache = $list;

        return $list;
    }

    /**
     * Updates a set of keys with new values. This will
     * only store settings that are allowed.
     */
    public static function update(array $data)
    {
        $class = get_class();

        foreach ($data as $key => $value) {
            // The post data converts periods to underscores
            $key = str_replace('_', '.', $key);

            if (! in_array($key, self::ALLOWED)) {
                continue;
            }

            $currentValue = self::get($key);

            // Update the value if one exists, but only if
            // the value has changed.
            if (null !== $currentValue) {
                if ((string) $currentValue === (string) $value) {
                    continue;
                }

                self::getDb()
                    ->update(['value' => $value])
                    ->table('meta')
                    ->where('key', '=', $key)
                    ->execute();
            } else {
                self::getDb()
                    ->insert(['key', 'value'])
                    ->into('meta')
                    ->values([$key, $value])
                    ->execute();
            }

            if (is_null(self::$cache)) {
                self::$cache = new stdClass;
            }

            self::$cache->$key = $value;
        }
    }
}
