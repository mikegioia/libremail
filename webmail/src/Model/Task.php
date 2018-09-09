<?php

namespace App\Model;

use PDO;
use DateTime;
use Exception;
use App\Model;
use App\Exceptions\ValidationException;

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

    /**
     * @var Shared batch ID across batch inserted tasks
     */
    private static $batchId;

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

    /**
     * Create a new task.
     *
     * @return Task
     */
    public static function create(
        int $messageId,
        int $accountId,
        string $type,
        string $oldValue = null,
        int $folderId = null)
    {
        $data = [
            'type' => $type,
            'old_value' => $oldValue,
            'folder_id' => $folderId,
            'message_id' => $messageId,
            'account_id' => $accountId,
            'status' => self::STATUS_NEW,
            'batch_id' => static::getBatchId(),
            'created_at' => (new DateTime)->format(DATE_DATABASE)
        ];

        $newTaskId = self::getDb()
            ->insert(array_keys($data))
            ->into('tasks')
            ->values(array_values($data))
            ->execute();

        if (! $newTaskId) {
            throw new Exception('Failed adding task');
        }

        $data['id'] = $newTaskId;

        return new static($data);
    }

    public function getByBatchId(int $batchId)
    {
        return $this->db()
            ->select()
            ->from('tasks')
            ->where('batch_id', '=', $batchId)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());
    }

    /**
     * The batch ID is used for multiple task inserts.
     * It gets set before the tasks are created and is
     * used for the entire request. The batch ID is used
     * to "undo" a collection of tasks that could have been
     * performed.
     *
     * @throws ValidationException
     */
    public static function getBatchId()
    {
        if (isset(self::$batchId) && is_numeric(self::$batchId)) {
            return self::$batchId;
        }

        $batch = Batch::create();

        self::$batchId = $batch->id;

        return self::$batchId;
    }

    /**
     * Updates the status of the task to done.
     */
    public function revert()
    {
        $this->updateStatus(self::STATUS_REVERTED);
    }

    /**
     * Updates the status of the message.
     */
    private function updateStatus(string $status)
    {
        $updated = $this->db()
            ->update([
                'status' => $status
            ])
            ->table('tasks')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }
}
