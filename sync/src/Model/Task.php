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

    public function requiresImapMessage()
    {
        return in_array($this->type, [
            self::TYPE_COPY,
            self::TYPE_FLAG,
            self::TYPE_READ,
            self::TYPE_CREATE,
            self::TYPE_DELETE,
            self::TYPE_UNFLAG,
            self::TYPE_UNREAD,
            self::TYPE_UNDELETE
        ]);
    }

    public function requiresOutboxMessage()
    {
        return in_array($this->type, [
            self::TYPE_SEND,
            self::TYPE_DELETE_OUTBOX
        ]);
    }

    public function revert()
    {
        $this->updateStatus(self::STATUS_REVERTED);

        return $this;
    }

    public function ignore(string $message = null)
    {
        $this->updateStatus(self::STATUS_IGNORED, $message);

        return $this;
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

    public function isFailed()
    {
        return self::STATUS_ERROR === $this->status;
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
        if (! $this->id) {
            return;
        }

        $this->status = $status;
        $updates = ['status' => $status];

        if ($reason || $force) {
            $this->reason = $reason;
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
     * Returns the un-synced tasks. Returns false if none.
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
     * Returns count of un-synced tasks, 0 if none.
     *
     * @param int $accountId
     *
     * @return int
     */
    public function getTaskCountForSync(int $accountId)
    {
        $result = $this->db()
            ->select()
            ->clear()
            ->count('1', 'count')
            ->from('tasks')
            ->where('account_id', '=', $accountId)
            ->where('status', '=', self::STATUS_NEW)
            ->execute()
            ->fetch();

        return intval($result['count'] ?? 0);
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
