<?php

namespace App\Model;

use Fn;
use PDO;
use DateTime;
use App\Model;
use Particle\Validator\Validator;
use App\Traits\Model as ModelTrait;
use App\Exceptions\NotFound as NotFoundException;
use App\Exceptions\Validation as ValidationException;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Folder extends Model
{
    use ModelTrait;

    public $id;
    public $name;
    public $count;
    public $synced;
    public $deleted;
    public $ignored;
    public $account_id;
    public $created_at;

    const DRAFTS = [
        '[Gmail]/Drafts',
        '[Gmail]/Bozze'
    ];

    const SENT = [
        '[Gmail]/Sent Mail',
        '[Gmail]/Posta inviata'
    ];

    public function getData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deleted' => $this->deleted,
            'ignored' => $this->ignored,
            'account_id' => $this->account_id,
            'created_at' => $this->created_at
        ];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCount()
    {
        return $this->count
            ? (int) $this->count
            : 0;
    }

    public function getSynced()
    {
        return $this->synced
            ? (int) $this->synced
            : 0;
    }

    public function getAccountId()
    {
        return (int) $this->account_id;
    }

    public function isIgnored()
    {
        return Fn\intEq($this->ignored, 1);
    }

    public function isDrafts()
    {
        return in_array($this->getName(), self::DRAFTS);
    }

    public function isSent()
    {
        return in_array($this->getName(), self::SENT);
    }

    /**
     * @throws NotFoundException
     */
    public function loadById()
    {
        if (! $this->id) {
            throw new NotFoundException(FOLDER);
        }

        $folder = $this->getById($this->id);

        if ($folder) {
            $this->setData($folder);
        } else {
            throw new NotFoundException(FOLDER);
        }

        return $this;
    }

    public function getById(int $id)
    {
        if ($id <= 0) {
            return;
        }

        return $this->db()
            ->select()
            ->from('folders')
            ->where('id', '=', $id)
            ->execute()
            ->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Create a new folder record. Updates a folder to be active
     * if it exists in the system already.
     *
     * @param array $data
     *
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save(array $data = [])
    {
        $val = new Validator;

        $val->optional('count', 'Count')->integer();
        $val->optional('ignored', 'Ignored')->numeric();
        $val->optional('synced', 'Synced count')->numeric();
        $val->required('account_id', 'Account ID')->numeric();
        $val->required('name', 'Name')->lengthBetween(0, 255);

        $this->setData($data);

        $data = $this->getData();

        if (! $val->validate($data)) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    'This folder is missing required data.'
                ));
        }

        // Check if this folder exists
        $exists = $this->db()
            ->select()
            ->from('folders')
            ->where('deleted', '=', 0)
            ->where('name', '=', $this->name)
            ->where('account_id', '=', $this->account_id)
            ->execute()
            ->fetchObject();

        // If it exists, unset deleted
        if ($exists) {
            $this->deleted = 0;
            $this->id = $exists->id;
            $this->ignored = $exists->ignored;

            $updated = $this->db()
                ->update([
                    'deleted' => 0,
                    'count' => $this->getCount(),
                    'synced' => $this->getSynced()
                ])
                ->table('folders')
                ->where('id', '=', $this->id)
                ->execute();

            if (false === $updated) {
                throw new DatabaseUpdateException(FOLDER);
            }

            return;
        }

        $createdAt = new DateTime;

        unset($data['id']);

        $data['deleted'] = 0;
        $data['created_at'] = $createdAt->format(DATE_DATABASE);
        $newFolderId = $this->db()
            ->insert(array_keys($data))
            ->into('folders')
            ->values(array_values($data))
            ->execute();

        if (! $newFolderId) {
            throw new DatabaseInsertException(FOLDER);
        }

        $this->id = $newFolderId;
    }

    public function delete()
    {
        $this->deleted = 1;

        $updated = $this->db()
            ->update([
                'deleted' => 1
            ])
            ->table('folders')
            ->where('id', '=', $this->id)
            ->execute();

        if (false === $updated) {
            throw new DatabaseUpdateException(FOLDER);
        }
    }

    /**
     * Stores the meta information about the folder. This includes
     * the total count of messages on the IMAP server, and the
     * count of messages confirmed to be synced in our database.
     *
     * @param int $count
     * @param int $synced
     *
     * @return bool
     */
    public function saveStats(int $count, int $synced)
    {
        $this->count = $count;
        $this->synced = $synced;

        return $this->save();
    }

    /**
     * Finds a folder by account and name.
     *
     * @param int $accountId
     * @param string $name
     * @param bool $failOnNotFound If set, throw an Exception when
     *   the folder isn't found
     *
     * @return bool | FolderModel
     */
    public function getByName(
        int $accountId,
        string $name,
        bool $failOnNotFound = false
    ) {
        $this->requireInt($accountId, 'Account ID');
        $this->requireString($name, 'Folder name');

        $folder = $this->db()
            ->select()
            ->from('folders')
            ->where('name', '=', $name)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchObject($this->getClass());

        $this->handleNotFound($folder, FOLDER, $failOnNotFound);

        return $folder;
    }

    /**
     * Returns a list of folders by an account ID. This is useful for
     * comparing the new folders against the already saved ones.
     *
     * @param int $accountId
     * @param bool $indexByName Return array indexed by folder name
     *
     * @return FolderModel array
     */
    public function getByAccount(int $accountId, bool $indexByName = true)
    {
        $this->requireInt($accountId, 'Account ID');

        $indexed = [];
        $folders = $this->db()
            ->select()
            ->from('folders')
            ->where('deleted', '=', 0)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, $this->getClass());

        if (! $indexByName) {
            return $folders;
        }

        foreach ($folders as $folder) {
            $indexed[$folder->getName()] = $folder;
        }

        ksort($indexed);

        return $indexed;
    }

    public function getSentByAccount(int $accountId)
    {
        $folders = $this->getByAccount($accountId);

        foreach ($folders as $folder) {
            if ($folder->isSent()) {
                return $folder;
            }
        }

        throw new NotFoundException('sent mail folder');
    }
}
