<?php

namespace App\Model;

use App\Model;
use PDO;

class Folder extends Model
{
    public $id;
    public $name;
    public $count;
    public $synced;
    public $deleted;
    public $ignored;
    public $account_id;
    public $created_at;
    public $uid_validity;

    // Computed fields added during thread computation
    public $color = '';
    public $full_name = '';
    public $is_mailbox = false;
    public $label = '';
    public $unread_count = 0;

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
        $indexed = [];
        $folders = $this->db()
            ->select()
            ->from('folders')
            ->where('deleted', '=', 0)
            ->where('ignored', '=', 0)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchAll(PDO::FETCH_CLASS, get_class());

        if (! $indexByName) {
            return $folders;
        }

        foreach ($folders as $folder) {
            $indexed[$folder->name] = $folder;
        }

        ksort($indexed);

        return $indexed;
    }

    public function countByAccount(int $accountId)
    {
        $response = $this->db()
            ->select(['count(1) as count'])
            ->from('folders')
            ->where('deleted', '=', 0)
            ->where('ignored', '=', 0)
            ->where('account_id', '=', $accountId)
            ->execute()
            ->fetchObject();

        return $response->count ?? 0;
    }
}
