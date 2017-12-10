<?php

namespace App\Model;

use Fn;
use PDO;
use DateTime;
use App\Model;
use Belt\Belt;
use Slim\PDO\Database;
use App\Traits\Model as ModelTrait;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;

class Task extends Model
{
    public $id;
    public $type;
    public $status;
    public $old_value;
    public $folder_id;
    public $account_id;
    public $message_id;
    public $created_at;

    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = 2;
    const STATUS_REVERTED = 3;

    const TYPE_COPY = 'copy';
    const TYPE_READ = 'read';
    const TYPE_FLAG = 'flag';
    const TYPE_DELETE = 'delete';
    const TYPE_UNFLAG = 'unflag';
    const TYPE_UNREAD = 'unread';
    const TYPE_UNDELETE = 'undelete';

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'old_value' => $this->old_value,
            'folder_id' => $this->folder_id,
            'account_id' => $this->account_id,
            'message_id' => $this->message_id,
            'created_at' => $this->created_at
        ];
    }

    /**
     * Updates the status of the task to done.
     */
    public function revert()
    {
        $this->updateStatus( self::STATUS_REVERTED );
    }

    /**
     * Updates the status of the message.
     * @param string $status
     * @throws DatabaseUpdateException
     */
    private function updateStatus( $status )
    {
        $updated = $this->db()
            ->update([
                'status' => $status
            ])
            ->table( 'tasks' )
            ->where( 'id', '=', $this->id )
            ->execute();

        if ( ! Belt::isNumber( $updated ) ) {
            throw new DatabaseUpdateException(
                TASK,
                $this->db()->getError() );
        }
    }

    /**
     * Returns the last un-synced task to be rolled back.
     * If there are none, this returns false.
     * @return array | bool
     */
    public function getTasksForRollback()
    {
        return $this->db()
            ->select()
            ->from( 'tasks' )
            ->where( 'status', '=', self::STATUS_NEW )
            ->orderBy( 'id', Model::DESC )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, $this->getClass() );
    }
}