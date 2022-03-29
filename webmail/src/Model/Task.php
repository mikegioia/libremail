<?php

namespace App\Model;

use App\Exceptions\ValidationException;
use App\Model;
use DateTime;
use Exception;
use PDO;

class Task extends Model
{
    public $id;
    public $type;
    public $status;
    public $reason;
    public $retries;
    public $folder_id;
    public $outbox_id;
    public $old_value;
    public $account_id;
    public $message_id;
    public $created_at;

    /**
     * @var Shared batch ID across batch inserted tasks
     */
    private static $batchId;

    public const STATUS_NEW = 0;
    public const STATUS_DONE = 1;
    public const STATUS_ERROR = 2;
    public const STATUS_REVERTED = 3;
    public const STATUS_IGNORED = 4;

    public const TYPE_COPY = 'copy';
    public const TYPE_FLAG = 'flag';
    public const TYPE_READ = 'read';
    public const TYPE_SEND = 'send';
    public const TYPE_CREATE = 'create';
    public const TYPE_DELETE = 'delete';
    public const TYPE_UNFLAG = 'unflag';
    public const TYPE_UNREAD = 'unread';
    public const TYPE_UNDELETE = 'undelete';
    public const TYPE_DELETE_OUTBOX = 'delete_outbox';

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
        int $folderId = null,
        int $outboxId = null
    ) {
        $data = [
            'type' => $type,
            'old_value' => $oldValue,
            'folder_id' => $folderId,
            'outbox_id' => $outboxId,
            'message_id' => $messageId,
            'account_id' => $accountId,
            'status' => self::STATUS_NEW,
            'batch_id' => self::getBatchId(),
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

        return new self($data);
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
            ->update(['status' => $status])
            ->table('tasks')
            ->where('id', '=', $this->id)
            ->execute();

        return is_numeric($updated) ? $updated : false;
    }
}
