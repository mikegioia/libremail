<?php

namespace App\Model;

use PDO;
use App\Model;

class Meta extends Model
{
    public $key;
    public $value;
    public $updated_at;

    /**
     * Get the entire set of metadata records transformed
     * into key/value pairs.
     *
     * @return object
     */
    public static function getAll()
    {
        $list = (object) [];
        $items = self::getDb()
            ->select()
            ->from('meta')
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());

        foreach ($items as $item) {
            $list->{$item->key} = $item->value;
        }

        return $list;
    }
}
