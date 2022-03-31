<?php

namespace App\Model;

use App\Exceptions\DatabaseInsert as DatabaseInsertException;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;
use App\Exceptions\NotFound as NotFoundException;
use App\Exceptions\Validation as ValidationException;
use App\Model;
use App\Traits\Model as ModelTrait;
use App\Util;
use DateTime;
use Particle\Validator\Validator;
use PDO;

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
    public $uid_validity;

    public const DRAFTS = [
        '[Gmail]/Drafts',
        '[Gmail]/Bozze'
    ];

    public const SENT = [
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
            'created_at' => $this->created_at,
            'uid_validity' => $this->uid_validity
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

    public function getUidValidity()
    {
        return (int) $this->uid_validity;
    }

    public function isIgnored()
    {
        return Util::intEq($this->ignored, 1);
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
            $this->setData((array) $folder);
        } else {
            throw new NotFoundException(FOLDER);
        }

        return $this;
    }

    /**
     * @return object|null
     */
    public function getById(int $id)
    {
        if ($id <= 0) {
            return null;
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
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save(array $data = []): void
    {
        $val = new Validator();

        $val->required('account_id', 'Account ID')->numeric();
        $val->required('name', 'Name')->lengthBetween(0, 255);

        $val->optional('count', 'Count')->integer();
        $val->optional('ignored', 'Ignored')->numeric();
        $val->optional('synced', 'Synced count')->numeric();
        $val->optional('uid_validity', 'UID validity')->numeric();

        $this->setData($data);

        $data = $this->getData();
        $result = $val->validate($data);

        if (! $result->isValid()) {
            $message = $this->getErrorString(
                $result,
                'This folder is missing required data.'
            );

            throw new ValidationException($message);
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

        // In all cases, unset deleted flag
        $data['deleted'] = 0;

        // If it exists, unset deleted
        if ($exists) {
            $this->deleted = 0;
            $this->id = $exists->id;
            $this->ignored = $exists->ignored;

            $data['count'] = $this->getCount();
            $data['synced'] = $this->getSynced();

            $updated = $this->db()
                ->update($data)
                ->table('folders')
                ->where('id', '=', $this->id)
                ->execute();

            if (false === $updated) {
                throw new DatabaseUpdateException(FOLDER);
            }

            return;
        }

        unset($data['id']);

        $data['created_at'] = (new DateTime())->format(DATE_DATABASE);

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

    public function delete(): void
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
     */
    public function saveStats(int $count, int $synced): void
    {
        $this->count = $count;
        $this->synced = $synced;

        $this->save();
    }

    /**
     * Updates a new UID validity flag on the folder.
     */
    public function saveUidValidity(int $uidValidity): void
    {
        $this->uid_validity = $uidValidity;

        $this->save();
    }

    /**
     * Finds a folder by account and name.
     *
     * @param bool $failOnNotFound If set, throw an Exception when
     *   the folder isn't found
     *
     * @return bool|Folder
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
     * @param bool $indexByName Return array indexed by folder name
     *
     * @return array<Folder>
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

    /**
     * @throws NotFoundException
     *
     * @return Folder
     */
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
