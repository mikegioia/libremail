<?php

namespace App\Model;

use App\Model;
use DateTime;
use Exception;

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

        return new self([
            'id' => $newBatchId,
            'created_at' => $createdAt
        ]);
    }
}
