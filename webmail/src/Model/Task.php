<?php

namespace App\Model;

use PDO;
use DateTime;
use Exception;
use App\Model;

class Task extends Model
{
    public $id;
    public $type;
    public $status;
    public $folder_id;
    public $old_value;
    public $account_id;
    public $message_id;
    public $created_at;

    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = 2;

    const TYPE_COPY = 'copy';
    const TYPE_READ = 'read';
    const TYPE_FLAG = 'flag';
    const TYPE_DELETE = 'delete';
    const TYPE_UNFLAG = 'unflag';
    const TYPE_UNREAD = 'unread';
    const TYPE_UNDELETE = 'undelete';

    /**
     * Create a new task.
     * @param int $messageId
     * @param int $accountId
     * @param string $type
     * @param string $oldValue
     * @param int | null $folderId
     * @return Task
     */
    public function create( $messageId, $accountId, $type, $oldValue, $folderId = NULL )
    {
        $data = [
            'type' => $type,
            'old_value' => $oldValue,
            'folder_id' => $folderId,
            'message_id' => $messageId,
            'account_id' => $accountId,
            'status' => self::STATUS_NEW,
            'created_at' => (new DateTime)->format( DATE_DATABASE )
        ];

        $newTaskId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'tasks' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newTaskId ) {
            throw new Exception( "Failed adding task" );
        }

        $data[ 'id' ] = $newTaskId;

        return new static( $data );
    }
}