<?php

namespace App\Model;

use PDO;
use DateTime;
use App\Model;

class Task extends Model
{
    public $id;
    public $type;
    public $status;
    public $folders;
    public $old_value;
    public $account_id;
    public $message_id;
    public $created_at;

    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = 2;

    const TYPE_COPY = 'copy';
    const TYPE_MOVE = 'move';
    const TYPE_READ = 'read';
    const TYPE_FLAG = 'flag';
    const TYPE_DELETE = 'delete';
    const TYPE_UNFLAG = 'unflag';
    const TYPE_UNREAD = 'unread';

    /**
     * Create a new task.
     */
    public function create( MessageModel $message, $type, $oldValue, $folders = NULL )
    {
        $data = [
            'type' => $type,
            'folders' => $folders,
            'old_value' => $oldValue,
            'status' => self::STATUS_NEW,
            'message_id' => $message->id,
            'account_id' => $message->account_id,
            'created_at' => (new DateTime)->->format( DATE_DATABASE );
        ];

        $newTaskId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'tasks' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newTaskId ) {
            throw new DatabaseInsertException(
                ACCOUNT,
                $this->getError() );
        }

        $data[ 'id' ] = $newTaskId;

        return new static( $data );
    }
}