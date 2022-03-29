<?php

namespace App\Model;

use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use App\Model;
use App\Traits\Model as ModelTrait;
use App\Util;

class Meta extends Model
{
    use ModelTrait;

    public $key;
    public $value;
    public $updated_at;

    public const ASLEEP = 'asleep';
    public const RUNNING = 'running';
    public const SYNC_PID = 'sync_pid';
    public const HEARTBEAT = 'heartbeat';
    public const START_TIME = 'start_time';
    public const FOLDER_STATS = 'folder_stats';
    public const ACTIVE_FOLDER = 'active_folder';
    public const ACTIVE_ACCOUNT = 'active_account';
    public const LAST_SYNC_TIME = 'last_sync_time';

    public function getData()
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Updates a set of keys with new values.
     *
     * @throws DatabaseUpdateException
     */
    public function update(array $data)
    {
        foreach ($data as $key => $value) {
            $updated = $this->db()
                ->update([
                    'value' => $value
                ])
                ->table('meta')
                ->where('key', '=', $key)
                ->execute();

            if (! Util::isNumber($updated)) {
                throw new DatabaseUpdateException(META, $this->db()->getError());
            }
        }
    }
}
