<?php

namespace App;

use App\Model\Folder;
use App\Model\Account;
use App\Model\Message;

class Folders
{
    private $inbox;
    private $allId;
    private $colors;
    private $sentId;
    private $spamId;
    private $folders;
    private $inboxId;
    private $trashId;
    private $draftsId;
    private $starredId;
    private $accountId;
    private $folderTree;
    private $colorCount;
    private $nameLookup;
    private $listFolders;
    // Storage of folder/depth
    private $index = [];
    // Convert certain folder names
    private $convert = [
        'INBOX' => 'Inbox'
    ];
    // Mailbox constants
    const INBOX = 'inbox';
    const GMAIL = '[gmail]';
    const DRAFTS = [
        '[Gmail]/Drafts'
    ];
    const SPAM = [
        '[Gmail]/Spam'
    ];
    const TRASH = [
        '[Gmail]/Trash'
    ];
    const STARRED = [
        '[Gmail]/Starred'
    ];
    const ALL = [
        '[Gmail]/All Mail'
    ];
    const SENT = [
        '[Gmail]/Sent Mail'
    ];

    /**
     * @param Account $account
     */
    public function __construct( Account $account, array $colors )
    {
        $this->colors = $colors;
        $this->accountId = $account->id;
        $this->colorCount = count( $colors );
    }

    public function getAllId()
    {
        return $this->getMailboxId( 'allId' );
    }

    public function getSentId()
    {
        return $this->getMailboxId( 'sentId' );
    }

    public function getSpamId()
    {
        return $this->getMailboxId( 'spamId' );
    }

    public function getInboxId()
    {
        return $this->getMailboxId( 'inboxId' );
    }

    public function getTrashId()
    {
        return $this->getMailboxId( 'trashId' );
    }

    public function getDraftsId()
    {
        return $this->getMailboxId( 'draftsId' );
    }

    public function getStarredId()
    {
        return $this->getMailboxId( 'starredId' );
    }

    private function getMailboxId( $mailbox )
    {
        if ( isset( $this->{$mailbox} ) ) {
            return $this->{$mailbox};
        }

        $this->get();

        return $this->{$mailbox};
    }

    public function get()
    {
        if ( $this->folders ) {
            return $this->folders;
        }

        $index = 0;
        $this->folders = (new Folder)->getByAccount( $this->accountId );
        $this->folderCounts = (new Message)->getUnreadCounts( $this->accountId );

        // Add meta info to the folders
        foreach ( $this->folders as $folder ) {
            $this->setIgnore( $folder );
            $this->setMailboxType( $folder );
            $this->addUnreadCount( $folder );
        }

        // Treeify the folders to set the depth. We need this for
        // adding in the color info.
        $this->getTree();

        // Add in the colors now that we know the positions
        foreach ( $this->folders as $folder ) {
            $this->setColor( $folder );
        }

        return $this->folders;
    }

    public function getTree()
    {
        if ( $this->folderTree ) {
            return $this->folderTree;
        }

        $index = 0;
        $this->folderTree = [];
        $folders = $this->get();

        foreach ( $folders as $folder ) {
            $parts = explode( '/', $folder->name );
            $this->treeify( $this->folderTree, $parts, $folder, 1 );
        }

        // Update the position and depth on each folder
        $offset = 0;
        $parentOffset = count( $this->folderTree );

        foreach ( $this->folderTree as $branch ) {
            $this->updateTreePositions( $branch, $index, $offset, $parentOffset );
        }

        return $this->folderTree;
    }

    /**
     * Returns a list of folders for the apply labels / move forms.
     * @return array
     */
    public function getList()
    {
        if ( $this->listFolders ) {
            return $this->listFolders;
        }

        $this->listFolders = array_filter(
            $this->get(),
            function ( $folder ) {
                return $folder->ignored != 1
                    && $folder->is_mailbox === FALSE;
            });

        return $this->listFolders;
    }

    /**
     * Returns a folder by ID.
     * @param int $id
     * @return Folder | null
     */
    public function getById( $id )
    {
        // Load folders if not set
        $this->get();

        foreach ( $this->folders as $folder ) {
            if ( $folder->id == $id ) {
                return $folder;
            }
        }
    }

    /**
     * Returns a folder ID by full name.
     * @param string $name
     * @return int $folderId
     */
    public function findIdByName( $name )
    {
        if ( $this->nameLookup ) {
            return ( isset( $this->nameLookup[ $name ] ) )
                ? $this->nameLookup[ $name ]
                : NULL;
        }

        $folders = $this->get();

        foreach ( $folders as $folder ) {
            $this->nameLookup[ $folder->full_name ] = $folder->id;
        }

        return ( isset( $this->nameLookup[ $name ] ) )
            ? $this->nameLookup[ $name ]
            : NULL;
    }

    /**
     * Returns the count of unread messages for a folder.
     * @param int $folderId
     * @param bool $returnString If true, returns a formatted string
     * @return int | string
     */
    public function getUnreadCount( $folderId, $returnString = FALSE )
    {
        if ( ! is_array( $this->folderCounts ) ) {
            $this->get();
        }

        $count = ( isset( $this->folderCounts[ $folderId ] ) )
            ? $this->folderCounts[ $folderId ]
            : 0;

        return ( $returnString )
            ? ( $count ? " ($count)" : "" )
            : ( $count ?: 0 );
    }

    /**
     * Returns a string of HTML classes for the folders in the nav.
     * @param int $folderId
     * @param int $activeId
     * @param bool $children If the folder has children
     * @return string
     */
    public function getNavClassString( $folderId, $activeId, $children = FALSE )
    {
        $classes = [];

        if ( $folderId == $activeId ) {
            $classes[] = 'active';
        }

        if ( $this->getUnreadCount( $folderId ) ) {
            $classes[] = 'w-unread';
        }

        if ( $children ) {
            $classes[] = 'w-sub';
        }

        return implode( ' ', $classes );
    }

    /**
     * Recursive function to build the folder tree.
     * @param array $tree
     * @param array $parts
     * @param Folder $folder
     * @param int $depth
     * @return array
     */
    private function treeify( &$tree, $parts, $folder, $depth )
    {
        $part = array_shift( $parts );

        if ( ! isset( $tree[ $part ] ) ) {
            $tree[ $part ] = [
                'children' => [],
                'depth' => $depth
            ];
        }

        // No more parts left
        if ( ! $parts ) {
            $this->fixName( $folder, $part );
            $tree[ $part ][ 'folder' ] = $folder;
        }
        // Recurse again
        else {
            $this->treeify(
                $tree[ $part ][ 'children' ],
                $parts,
                $folder,
                $depth + 1 );
        }
    }

    /**
     * Stores positional info on the tree folders. This is used for
     * determining the folder color.
     * @param array $branch
     * @param int $index
     * @param int $offset
     * @param int $parentOffset
     */
    private function updateTreePositions( $branch, &$index, $offset, $parentOffset )
    {
        if ( $branch[ 'folder' ]->is_mailbox ) {
            return;
        }

        $index++;
        $childIndex = 0;
        $this->index[ $branch[ 'folder' ]->id ] = (object) [
            'pos' => $index,
            'offset' => $offset,
            'depth' => $branch[ 'depth' ]
        ];

        $offset = $parentOffset;
        $parentOffset = count( $branch[ 'children' ] );

        foreach ( $branch[ 'children' ] as $child ) {
            $this->updateTreePositions(
                $child,
                $childIndex,
                $offset,
                $parentOffset );
        }
    }

    /**
     * Convert certain folder names for better readability.
     * @param Folder $folder
     * @param string $finalPart
     */
    private function fixName( &$folder, $finalPart )
    {
        $folder->full_name = $folder->name;
        $folder->name = $finalPart;
        $folder->name = str_replace(
            array_keys( $this->convert ),
            array_values( $this->convert ),
            $folder->name );

        // Shortened label for display in the inbox
        $parts = explode( '/', $folder->full_name );
        $partCount = count( $parts );
        $folder->label = ( $partCount > 2 )
            ? $parts[ 0 ] .'/&hellip;/'. $parts[ $partCount - 1 ]
            : $folder->full_name;
    }

    /**
     * Determines if the folder is a mailbox or a regular folder.
     * Adds a property to the folder 'is_mailbox'.
     * @param Folder $folder
     */
    private function setMailboxType( &$folder )
    {
        $name = $folder->name;

        if ( strtolower( $name ) === self::INBOX
            && ! $this->inboxId )
        {
            $this->inbox =& $folder;
            $folder->is_mailbox = TRUE;
            $this->inboxId = $folder->id;
        }
        // Special case for GMail
        elseif ( strpos( strtolower( $name ), self::GMAIL ) === 0 ) {
            $folder->is_mailbox = TRUE;
        }
        else {
            $folder->is_mailbox = FALSE;
        }

        // Set special IDs for certain mailboxes
        if ( $folder->is_mailbox ) {
            if ( ! $this->allId && in_array( $name, self::ALL ) ) {
                $this->allId = $folder->id;
            }
            elseif ( ! $this->sentId && in_array( $name, self::SENT ) ) {
                $this->sentId = $folder->id;
            }
            elseif ( ! $this->spamId && in_array( $name, self::SPAM ) ) {
                $this->spamId = $folder->id;
            }
            elseif ( ! $this->trashId && in_array( $name, self::TRASH ) ) {
                $this->trashId = $folder->id;
            }
            elseif ( ! $this->draftsId && in_array( $name, self::DRAFTS ) ) {
                $this->draftsId = $folder->id;
            }
            elseif ( ! $this->starredId && in_array( $name, self::STARRED ) ) {
                $this->starredId = $folder->id;
            }
        }
    }

    private function addUnreadCount( &$folder )
    {
        if ( isset( $this->folderCounts[ $folder->id ] ) ) {
            $folder->unread_count = $this->folderCounts[ $folder->id ];
        }
        else {
            $folder->unread_count = 0;
        }
    }

    /**
     * Ignores certain folders that shouldn't display.
     * @param Folder $folder
     */
    private function setIgnore( &$folder )
    {
        if ( strtolower( $folder->name ) === self::GMAIL ) {
            $folder->ignored = 1;
        }
    }

    /**
     * Adds a default color for the folder.
     * @param Folder $folder
     * @param int $index
     */
    private function setColor( &$folder )
    {
        if ( ! isset( $this->index[ $folder->id ] ) ) {
            return;
        }

        $index = $this->index[ $folder->id ];
        $position = $index->pos + $index->offset - 1;
        $color = $this->colors[ $position % $this->colorCount ];
        $folder->color = (object) $color;
    }
}