<?php

namespace App;

use App\Model\Folder;
use App\Model\Account;

class Folders
{
    private $colors;
    private $folders;
    private $inboxId;
    private $accountId;
    private $folderTree;
    private $colorCount;
    // Storage of folder/depth
    private $index = [];
    // Convert certain folder names
    private $convert = [
        'INBOX' => 'Inbox'
    ];
    // Mailbox constants
    const INBOX = 'inbox';
    const GMAIL = '[gmail]';

    /**
     * @param Account $account
     */
    public function __construct( Account $account, array $colors )
    {
        $this->colors = $colors;
        $this->accountId = $account->id;
        $this->colorCount = count( $colors );
    }

    public function get()
    {
        if ( $this->folders ) {
            return $this->folders;
        }

        $index = 0;
        $this->folders = (new Folder)->getByAccount( $this->accountId );

        // Add meta info to the folders
        foreach ( $this->folders as $folder ) {
            $this->setIgnore( $folder );
            $this->setMailboxType( $folder );
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

    public function getInboxId()
    {
        if ( $this->inboxId ) {
            return $this->inboxId;
        }

        // Sets inbox ID
        $this->get();

        return $this->inboxId;
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
    }

    /**
     * Determines if the folder is a mailbox or a regular folder.
     * Adds a property to the folder 'is_mailbox'.
     * @param Folder $folder
     */
    private function setMailboxType( &$folder )
    {
        if ( strtolower( $folder->name ) === self::INBOX ) {
            $folder->is_mailbox = TRUE;
            $this->inboxId = $folder->id;
        }
        // Special case for GMail
        elseif ( strpos( strtolower( $folder->name ), self::GMAIL ) === 0 ) {
            $folder->is_mailbox = TRUE;
        }
        else {
            $folder->is_mailbox = FALSE;
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