<?php

/**
 * Syncs IMAP folders to SQL.
 */

namespace App\Sync;

use Fn;
use App\Sync;
use Monolog\Logger;
use League\CLImate\CLImate;
use App\Model\Folder as FolderModel;
use App\Model\Account as AccountModel;
use Evenement\EventEmitter as Emitter;

class Folders
{
    private $log;
    private $cli;
    private $emitter;

    /**
     * @param Logger $log
     * @param CLImate $cli
     */
    public function __construct( Logger $log, CLImate $cli, Emitter $emitter )
    {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
    }

    /**
     * Syncs a set of IMAP folders with what we have in SQL.
     * @param array $folderList
     * @param FolderModel array $savedFolders
     * @param AccountModel $account
     */
    public function run( $folderList, $savedFolders, AccountModel $account )
    {
        $count = iterator_count( $folderList );
        $this->log->debug( "Found $count ". Fn\plural( 'folder', $count ) );
        $this->addNewFolders( $folderList, $savedFolders, $account );
        $this->removeOldFolders( $folderList, $savedFolders, $account );
    }

    /**
     * Adds new folders from IMAP to the database.
     * @param array $folderList
     * @param FolderModel array $savedFolders
     * @param AccountModel $account
     */
    private function addNewFolders( $folderList, $savedFolders, AccountModel $account )
    {
        $i = 1;
        $toAdd = [];

        foreach ( $folderList as $folderName ) {
            if ( ! array_key_exists( (string) $folderName, $savedFolders ) ) {
                $toAdd[] = $folderName;
            }
        }

        if ( ! ( $count = count( $toAdd ) ) ) {
            $this->log->debug( "No new folders to save" );
            return;
        }

        if ( $this->interactive ) {
            $this->cli->whisper(
                "Adding $count new ". Fn\plural( 'folder', $count ) .":" );
            $progress = $this->cli->progress()->total( $count );
        }
        else {
            $this->log->info(
                "Adding $count new ". Fn\plural( 'folder', $count ) );
        }

        foreach ( $toAdd as $folderName ) {
            $folder = new FolderModel([
                'name' => $folderName,
                'account_id' => $account->getId()
            ]);
            $folder->save();
            $folders[ $folder->getId() ] = $folder;

            if ( $this->interactive ) {
                $progress->current( $i++ );
            }

            $this->emitter->emit( Sync::EVENT_CHECK_HALT );
        }
    }

    /**
     * Removes purged folders no longer in the mailbox from the database.
     * @param array $folderList
     * @param FolderModel array $savedFolders
     * @param AccountModel $account
     */
    private function removeOldFolders( $folderList, $savedFolders, AccountModel $account )
    {
        $lookup = [];
        $toRemove = [];

        foreach ( $folderList as $folderName ) {
            $lookup[] = $folderName;
        }

        foreach ( $savedFolders as $savedFolder ) {
            if ( ! in_array( $savedFolder->getName(), $lookup ) ) {
                $toRemove[] = $savedFolder;
            }
        }

        if ( ! ( $count = count( $toRemove ) ) ) {
            $this->log->debug( "No folders to remove" );
            return;
        }

        $this->log->info( "Removing $count ". Fn\plural( 'folder', $count ) );

        foreach ( $toRemove as $folder ) {
            $folder->delete();
        }
    }
}