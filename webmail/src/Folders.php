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
    }

    public function get()
    {
        if ( $this->folders ) {
            return $this->folders;
        }

        $index = 0;
        $folders = (new Folder)->getByAccount( $this->accountId );

        // Add the colors
        foreach ( $folders as $folder ) {
            $this->setIgnore( $folder );
            $this->setMailboxType( $folder );
            $this->setColor( $folder, $index++ );
        }

        $this->folders = $folders;

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

        $this->folderTree = [];
        $folders = $this->get();

        foreach ( $folders as $folder ) {
            $parts = explode( '/', $folder->name );
            $this->treeify( $this->folderTree, $parts, $folder );
        }

        return $this->folderTree;
    }

    /**
     * Recursive function to build the folder tree.
     * @param array $tree
     * @param array $parts
     * @param Folder $folder
     * @return array
     */
    private function treeify( &$tree, $parts, $folder )
    {
        $part = array_shift( $parts );

        if ( ! isset( $tree[ $part ] ) ) {
            $tree[ $part ] = [
                'children' => []
            ];
        }

        // No more parts left
        if ( ! $parts ) {
            $this->fixName( $folder, $part );
            $tree[ $part ][ 'folder' ] = $folder;
        }
        // Recurse again
        else {
            $this->treeify( $tree[ $part ][ 'children' ], $parts, $folder );
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
    private function setColor( &$folder, $index )
    {
        $count = count( $this->colors );
        $color = $this->colors[ $index % $count ];
        $folder->color = $color[ 'bg' ];
    }
}