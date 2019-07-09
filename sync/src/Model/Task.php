<?php

namespace App\Model;

use PDO;
use Exception;
use App\Model;
use Belt\Belt;
use App\Traits\Model as ModelTrait;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;

class Task extends Model
{
    use ModelTrait;

    public $id;
    public $type;
    public $status;
    public $reason;
    public $retries;
    public $old_value;
    public $folder_id;
    public $outbox_id;
    public $account_id;
    public $message_id;
    public $created_at;

    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = 2;
    const STATUS_REVERTED = 3;
    const STATUS_IGNORED = 4;

    const TYPE_COPY = 'copy';
    const TYPE_FLAG = 'flag';
    const TYPE_READ = 'read';
    const TYPE_SEND = 'send';
    const TYPE_CREATE = 'create';
    const TYPE_DELETE = 'delete';
    const TYPE_UNFLAG = 'unflag';
    const TYPE_UNREAD = 'unread';
    const TYPE_UNDELETE = 'undelete';
    const TYPE_DELETE_OUTBOX = 'delete_outbox';

    const MAX_ATTEMPTS = 3;

    public function getData()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'reason' => $this->reason,
            'retries' => $this->retries,
            'old_value' => $this->old_value,
            'folder_id' => $this->folder_id,
            'outbox_id' => $this->outbox_id,
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
        $this->updateStatus(self::STATUS_REVERTED);
    }

    public function ignore()
    {
        $this->updateStatus(self::STATUS_IGNORED);
    }

    /**
     * Marks a message as failed.
     *
     * @param string $message
     * @param int $status
     * @param mixed $returnValue value returned from this function;
     *   Useful for the caller to respond with this function
     *
     * @throws DatabaseUpdateException
     *
     * @return mixed
     */
    public function fail(
        string $message,
        Exception $e = null,
        int $status = self::STATUS_ERROR,
        $returnValue = false
    ) {
        if ($e) {
            $message .= ' '.$e->getMessage();
        }

        $this->updateStatus($status, $message);

        return $returnValue;
    }

    /**
     * Marks a message as done.
     *
     * @throws DatabaseUpdateException
     *
     * @return bool
     */
    public function done()
    {
        $this->updateStatus(self::STATUS_DONE, null, true);

        return true;
    }

    /**
     * Increments the retry counter.
     *
     * @throws DatabaseUpdateException
     */
    public function retry()
    {
        $retries = $this->retries ?: 0;

        if ($retries + 1 > self::MAX_ATTEMPTS) {
            return $this->fail('Exceeded retry attempts');
        }

        $updated = $this->db()
            ->update(['retries' => $retries + 1])
            ->table('tasks')
            ->where('id', '=', $this->id)
            ->execute();

        if (! Belt::isNumber($updated)) {
            throw new DatabaseUpdateException(
                TASK,
                $this->db()->getError()
            );
        }
    }

    /**
     * Updates the status of the message.
     *
     * @param string $status
     * @param string $reason Optional reason string
     * @param bool $force If set, forces all fields to update
     *
     * @throws DatabaseUpdateException
     */
    private function updateStatus(
        string $status,
        string $reason = null,
        bool $force = false
    ) {
        $updates = ['status' => $status];

        if ($reason || $force) {
            $updates['reason'] = $reason;
        }

        $updated = $this->db()
            ->update($updates)
            ->table('tasks')
            ->where('id', '=', $this->id)
            ->execute();

        if (! Belt::isNumber($updated)) {
            throw new DatabaseUpdateException(
                TASK,
                $this->db()->getError()
            );
        }
    }

    /**
     * Returns the un-synced tasks. If there are none,
     * this returns false.
     *
     * @param int $accountId
     *
     * @return array | bool
     */
    public function getTasksForSync(int $accountId)
    {
        return $this->db()
            ->select()
            ->from('tasks')
            ->where('account_id', '=', $accountId)
            ->where('status', '=', self::STATUS_NEW)
            ->orderBy('id', Model::ASC)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, $this->getClass());
    }

    /**
     * Returns ALL of the un-synced tasks for a rollback.
     * If there are none, this returns false.
     *
     * @return array | bool
     */
    public function getTasksForRollback()
    {
        return $this->db()
            ->select()
            ->from('tasks')
            ->where('status', '=', self::STATUS_NEW)
            ->orderBy('id', Model::DESC)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, $this->getClass());
    }
}
