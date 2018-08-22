<?php

namespace App\Model;

use DateTime;
use App\Model;

class Batch extends Model
{
    public $id;
    public $created_at;

    public static function create()
    {
        $createdAt = (new DateTime)->format(DATE_DATABASE);

        $newBatchId = self::getDb()
            ->insert(['created_at'])
            ->into('batches')
            ->values([$createdAt])
            ->execute();

        if (! $newBatchId) {
            throw new Exception('Failed adding batch');
        }

        return new static([
            'id' => $newBatchId,
            'created_at' => $createdAt
        ]);
    }
}
