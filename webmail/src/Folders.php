<?php

namespace App;

use App\Model\Folder;
use App\Model\Outbox;
use App\Model\Account;
use App\Model\Message;

class Folders
{
    private $inbox;
    private $allId;
    private $colors;
    private $sentId;
    private $spamId;
    private $outbox;
    private $folders;
    private $inboxId;
    private $trashId;
    private $account;
    private $draftsId;
    private $starredId;
    private $accountId;
    private $folderTree;
    private $colorCount;
    private $nameLookup;
    private $listFolders;
    // Storage of folder/depth
    private $index = [];
    // Flag if the folders are fully loaded
    private $loaded = false;
    // Convert certain folder names
    private $convert = [
        'INBOX' => 'Inbox',
        '[Gmail]/Trash' => 'Trash',
        '[Gmail]/Cestino' => 'Cestino'
    ];
    // Mailbox constants
    const INBOX = 'inbox';
    const GMAIL = '[gmail]';
    const DRAFTS = [
        '[Gmail]/Drafts',
        '[Gmail]/Bozze'
    ];
    const SPAM = [
        '[Gmail]/Spam'
    ];
    const TRASH = [
        '[Gmail]/Trash',
        '[Gmail]/Cestino'
    ];
    const STARRED = [
        '[Gmail]/Starred',
        '[Gmail]/Speciali'
    ];
    const ALL = [
        '[Gmail]/All Mail',
        '[Gmail]/Tutti i messaggi'
    ];
    const SENT = [
        '[Gmail]/Sent Mail',
        '[Gmail]/Posta inviata'
    ];
    // Color constants
    const COLOR_GREY = [
        'name' => 'light-grey',
        'bg' => '#cdcfd4',
        'fg' => '#303138'
    ];

    public function __construct(Account $account, array $colors)
    {
        $this->colors = $colors;
        $this->account = $account;
        $this->accountId = $account->id;
        $this->colorCount = count($colors);
    }

    public function getAllId()
    {
        return $this->getMailboxId('allId');
    }

    public function getSentId()
    {
        return $this->getMailboxId('sentId');
    }

    public function getSpamId()
    {
        return $this->getMailboxId('spamId');
    }

    public function getInboxId()
    {
        return $this->getMailboxId('inboxId');
    }

    public function getTrashId()
    {
        return $this->getMailboxId('trashId');
    }

    public function getDraftsId()
    {
        return $this->getMailboxId('draftsId');
    }

    public function getStarredId()
    {
        return $this->getMailboxId('starredId');
    }

    private function getMailboxId(string $mailbox)
    {
        if (isset($this->{$mailbox})) {
            return (int) $this->{$mailbox};
        }

        $this->loadFolders();

        return (int) $this->{$mailbox};
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function getColors()
    {
        return $this->colors;
    }

    public function getOutbox()
    {
        if ($this->outbox) {
            return $this->outbox;
        }

        $this->outbox = new Outbox($this->account);

        return $this->outbox;
    }

    /**
     * Most queries for messages want to ignore certain folders.
     * For example, if a single message in a thread was trashed
     * we still want to display the rest of the messages in the
     * inbox.
     */
    public function getSkipIds(int $folderId = null)
    {
        $skipIds = array_filter([
            $this->getSpamId(),
            $this->getTrashId()
        ]);

        if ($folderId) {
            $skipIds = array_diff($skipIds, [$folderId]);
        }

        return array_values(array_filter($skipIds));
    }

    /**
     * Returns an array of folder IDs to restrict a query by.
     * For example, when viewing the trash folder, we only want
     * to pull messages from that folder.
     */
    public function getRestrictIds(int $folderId = null)
    {
        if ($folderId === $this->getTrashId()
            || $folderId === $this->getSpamId()
        ) {
            return [$folderId];
        }

        return [];
    }

    public function get(bool $withMeta = false)
    {
        if ($this->loaded) {
            return $this->folders;
        }

        $this->loadFolders();

        if ($withMeta) {
            $this->loadMetaTree();
        }

        return $this->folders;
    }

    public function getTree()
    {
        if ($this->folderTree) {
            return $this->folderTree;
        }

        $this->loadMetaTree();

        return $this->folderTree;
    }

    /**
     * Returns a list of folders for the apply labels / move forms.
     *
     * @return array
     */
    public function getList(array $selectedIds = [])
    {
        if ($this->listFolders) {
            return $this->applySelected($this->listFolders, $selectedIds);
        }

        $this->listFolders = array_filter(
            $this->get(),
            function ($folder) {
                return 1 != $folder->ignored
                    && false === $folder->is_mailbox;
            });

        return $this->applySelected($this->listFolders, $selectedIds);
    }

    /**
     * Returns a count of folders.
     *
     * @return int
     */
    public function getCount()
    {
        if ($this->folders) {
            return count($this->folders);
        }

        $this->loadFolders();

        return count($this->folders);
    }

    /**
     * Returns a folder by ID.
     *
     * @return Folder | null
     */
    public function getById(int $id)
    {
        // Load folders if not set
        $this->get();

        foreach ($this->folders as $folder) {
            if ($folder->id == $id) {
                return $folder;
            }
        }
    }

    /**
     * Returns a folder ID by full name.
     *
     * @return int $folderId
     */
    public function findIdByName(string $name)
    {
        if (! $this->loaded) {
            $this->loadMetaTree();
        }

        if ($this->nameLookup) {
            return isset($this->nameLookup[$name])
                ? $this->nameLookup[$name]
                : null;
        }

        $folders = $this->get();

        foreach ($folders as $folder) {
            $this->nameLookup[$folder->full_name] = $folder->id;
        }

        return isset($this->nameLookup[$name])
            ? $this->nameLookup[$name]
            : null;
    }

    /**
     * Returns the count of unread messages for a folder.
     *
     * @param bool $returnString If true, returns a formatted string
     *
     * @return int | string
     */
    public function getUnreadCount(int $folderId, bool $returnString = false)
    {
        if (! $this->loaded) {
            $this->loadMetaTree();
        }

        if (! is_array($this->folderCounts)) {
            $this->get();
        }

        $count = $this->folderCounts[$folderId] ?? 0;

        return $returnString
            ? ($count ? " ($count)" : '')
            : ($count ?: 0);
    }

    /**
     * Returns a string of HTML classes for the folders in the nav.
     *
     * @return string
     */
    public function getNavClassString(int $folderId, int $activeId, array $children = null)
    {
        $classes = [];

        if ($folderId == $activeId) {
            $classes[] = 'active';
        }

        if ($this->getUnreadCount($folderId)) {
            $classes[] = 'w-unread';
        }

        if ($children) {
            $classes[] = 'w-sub';
        }

        return implode(' ', $classes);
    }

    /**
     * Fetches the folders from SQL and adds basic indexing info.
     */
    private function loadFolders()
    {
        if ($this->folders) {
            return;
        }

        $this->folders = (new Folder)->getByAccount($this->accountId);

        // Add meta info to the folders
        foreach ($this->folders as $folder) {
            $this->setIgnore($folder);
            $this->setMailboxType($folder);
        }
    }

    /**
     * Fetches all of the counts and meta info for the folders,
     * and builds a separate storage of the folders as a tree.
     */
    private function loadMetaTree()
    {
        $this->loadFolders();
        $this->folderCounts = (new Message)->getUnreadCounts(
            $this->accountId,
            $this->getSkipIds(),
            $this->getDraftsId()
        );

        // Add meta info to the folders
        foreach ($this->folders as $folder) {
            $this->addUnreadCount($folder);
        }

        // Treeify the folders to set the depth. We need this for
        // adding in the color info.
        $this->loadTree();

        // Add in the colors now that we know the positions
        foreach ($this->folders as $folder) {
            $this->setColor($folder);
        }

        $this->loaded = true;
    }

    /**
     * Builds the folderTree object.
     */
    private function loadTree()
    {
        $index = 0;
        $offset = 0;
        $this->folderTree = [];

        foreach ($this->folders as $folder) {
            $parts = explode('/', $folder->name);
            $this->treeify($this->folderTree, $parts, $folder, 1);
        }

        // Update the position and depth on each folder
        $parentOffset = count($this->folderTree);

        foreach ($this->folderTree as $branch) {
            $this->updateTreePositions(
                $branch, $index, $offset, $parentOffset
            );
        }
    }

    /**
     * Recursive function to build the folder tree.
     *
     * @return array
     */
    private function treeify(
        array &$tree,
        array $parts,
        Folder $folder,
        int $depth
    ) {
        $part = array_shift($parts);

        if (! isset($tree[$part])) {
            $tree[$part] = [
                'children' => [],
                'depth' => $depth
            ];
        }

        // No more parts left
        if (! $parts) {
            $this->fixName($folder, $part);
            $tree[$part]['folder'] = $folder;
        } else { // Recurse again
            $this->treeify(
                $tree[$part]['children'],
                $parts,
                $folder,
                $depth + 1
            );
        }
    }

    /**
     * Stores positional info on the tree folders. This is used for
     * determining the folder color.
     */
    private function updateTreePositions(
        array $branch,
        int &$index,
        int $offset,
        int $parentOffset
    ) {
        if (isset($branch['folder'])) {
            if ($branch['folder']->is_mailbox) {
                return;
            }

            ++$index;
            $this->index[$branch['folder']->id] = (object) [
                'pos' => $index,
                'offset' => $offset,
                'depth' => $branch['depth']
            ];
        }

        $childIndex = 0;
        $offset = $parentOffset;
        $parentOffset = count($branch['children']);

        foreach ($branch['children'] as $child) {
            $this->updateTreePositions(
                $child,
                $childIndex,
                $offset,
                $parentOffset
            );
        }
    }

    /**
     * Convert certain folder names for better readability.
     */
    private function fixName(Folder &$folder, string $finalPart)
    {
        $folder->full_name = $folder->name;
        $folder->name = $finalPart;
        $folder->name = str_replace(
            array_keys($this->convert),
            array_values($this->convert),
            $folder->name
        );
        $folder->full_name = str_replace(
            array_keys($this->convert),
            array_values($this->convert),
            $folder->full_name
        );

        // Shortened label for display in the inbox
        $parts = explode('/', $folder->full_name);
        $partCount = count($parts);
        $folder->label = $partCount > 2
            ? $parts[0].'/&hellip;/'.$parts[$partCount - 1]
            : $folder->full_name;
    }

    /**
     * Determines if the folder is a mailbox or a regular folder.
     * Adds a property to the folder 'is_mailbox'.
     */
    private function setMailboxType(Folder &$folder)
    {
        $name = $folder->name;

        if (self::INBOX === strtolower($name) && ! $this->inboxId) {
            $this->inbox = &$folder;
            $folder->is_mailbox = true;
            $this->inboxId = $folder->id;
        } elseif (0 === strpos(strtolower($name), self::GMAIL)) {
            // Special case for GMail
            $folder->is_mailbox = true;
        } else {
            $folder->is_mailbox = false;
        }

        // Set special IDs for certain mailboxes
        if ($folder->is_mailbox) {
            if (! $this->allId && in_array($name, self::ALL)) {
                $this->allId = $folder->id;
            } elseif (! $this->sentId && in_array($name, self::SENT)) {
                $this->sentId = $folder->id;
            } elseif (! $this->spamId && in_array($name, self::SPAM)) {
                $this->spamId = $folder->id;
            } elseif (! $this->trashId && in_array($name, self::TRASH)) {
                $this->trashId = $folder->id;
            } elseif (! $this->draftsId && in_array($name, self::DRAFTS)) {
                $this->draftsId = $folder->id;
            } elseif (! $this->starredId && in_array($name, self::STARRED)) {
                $this->starredId = $folder->id;
            }
        }
    }

    private function addUnreadCount(Folder &$folder)
    {
        if (isset($this->folderCounts[$folder->id])) {
            $folder->unread_count = $this->folderCounts[$folder->id];
        } else {
            $folder->unread_count = 0;
        }
    }

    /**
     * Ignores certain folders that shouldn't display.
     */
    private function setIgnore(Folder &$folder)
    {
        if (self::GMAIL === strtolower($folder->name)) {
            $folder->ignored = 1;
        }
    }

    /**
     * Adds a default color for the folder.
     */
    private function setColor(Folder &$folder)
    {
        if (! isset($this->index[$folder->id])) {
            $folder->color = (object) self::COLOR_GREY;

            return;
        }

        if (! $this->colorCount) {
            $folder->color = null;

            return;
        }

        $index = $this->index[$folder->id];
        $position = $index->pos + $index->offset - 1;
        $color = $this->colors[$position % $this->colorCount];
        $folder->color = (object) $color;
    }

    /**
     * Adds the boolean property "selected" based on the IDs
     * passed in. Sorts the entire by selected items first.
     */
    private function applySelected(array $folderList, array $selectedIds)
    {
        $folders = [];

        foreach ($folderList as $folder) {
            $cloned = clone $folder;
            $cloned->selected = in_array($cloned->id, $selectedIds);
            $folders[] = $cloned;
        }

        if (! $selectedIds) {
            return $folders;
        }

        // Sory by selected first
        usort($folders, function ($a, $b) {
            return $b->selected <=> $a->selected;
        });

        return $folders;
    }
}
