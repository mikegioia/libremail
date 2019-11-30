<?php

namespace App\Model;

use App\Model;
use Belt\Belt;
use App\Traits\Model as ModelTrait;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;

class Meta extends Model
{
    use ModelTrait;

    public $key;
    public $value;
    public $updated_at;

    const ASLEEP = 'asleep';
    const RUNNING = 'running';
    const SYNC_PID = 'sync_pid';
    const HEARTBEAT = 'heartbeat';
    const START_TIME = 'start_time';
    const FOLDER_STATS = 'folder_stats';
    const ACTIVE_FOLDER = 'active_folder';
    const ACTIVE_ACCOUNT = 'active_account';
    const LAST_SYNC_TIME = 'last_sync_time';

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
     * @param array $data
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

            if (! Belt::isNumber($updated)) {
                throw new DatabaseUpdateException(
                    META,
                    $this->db()->getError()
                );
            }
        }
    }
}
