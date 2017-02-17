<?php

namespace App;

use Fn
  , App\Daemon
  , App\Console
  , App\Message
  , App\Message\StatsMessage
  , App\Model\Folder as FolderModel
  , App\Model\Account as AccountModel
  , App\Model\Message as MessageModel;

/**
 * Reports statistics about the syncing process. This will
 * be called via signals to print information.
 */
class Stats
{
    private $cli;
    private $stats;
    private $daemon;
    private $asleep;
    private $running;
    private $startTime;
    private $activeFolder;
    private $activeAccount;

    public function __construct( Console $console )
    {
        $this->cli = $console->getCli();
        $this->daemon = $console->daemon;
    }

    public function setAsleep( $asleep = TRUE )
    {
        $this->asleep = $asleep;
        $this->unsetActiveFolder();
    }

    public function setRunning( $running = TRUE )
    {
        $this->running = $running;

        if ( $running && ! $this->startTime ) {
            $this->startTime = time();
        }
    }

    public function setActiveAccount( $account )
    {
        $this->activeAccount = $account;

        if ( $this->daemon ) {
            $this->json( TRUE );
        }
    }

    public function setActiveFolder( $folder )
    {
        $this->activeFolder = $folder;

        if ( $this->daemon ) {
            $this->json();
        }
    }

    public function unsetActiveFolder()
    {
        $this->activeFolder = NULL;

        if ( $this->daemon ) {
            $this->json();
        }
    }

    /**
     * Returns all of the statistics and sync status info on
     * all of the folders for all of the accounts.
     * @param array $useCache If true, use it instead of fetching.
     *   This is useful when sending the message to the client
     *   like 'update the active folder' but without needing to
     *   query for all folder statistics.
     * @return array
     */
    public function getStats( $useCache = FALSE )
    {
        if ( $useCache === TRUE && ! is_null( $this->stats ) ) {
            return $this->stats;
        }

        // Get all of the accounts. For each, get all of the
        // folders and their statistics info. Build this into
        // an multi-array.
        $stats = [];
        $folderModel = new FolderModel;
        $accountModel = new AccountModel;
        $accounts = $accountModel->getActive();

        foreach ( $accounts as $account ) {
            $folderStats = [];
            $folders = $folderModel->getByAccount( $account->getId() );

            foreach ( $folders as $folder ) {
                if ( $folder->isIgnored() ) {
                    continue;
                }

                $folderStats[ $folder->getName() ] = [
                    'count' => $folder->getCount(),
                    'synced' => $folder->getSynced(),
                    'percent' => ( $folder->getSynced() > 0 )
                        ? Fn\percent( $folder->getSynced() / $folder->getCount() )
                        : (( $folder->getCount() > 0 )
                            ? "0%"
                            : "100%")
                ];
            }

            $stats[ $account->getEmail() ] = $folderStats;
        }

        $this->stats = $stats;

        return $stats;
    }

    /**
     * Prints the statistics as text to stdout.
     */
    public function text()
    {
        $stats = $this->getStats();

        foreach ( $stats as $account => $folders ) {
            $columns = [
                [ 'Folder', 'Count', 'Synced', '%' ]
            ];
            $this->cli->out( $account );

            foreach ( $folders as $folderName => $folderStats ) {
                $columns[] = [
                    $folderName,
                    $folderStats[ 'count' ],
                    $folderStats[ 'synced' ],
                    $folderStats[ 'percent' ]
                ];
            }

            $this->cli->columns( $columns );
        }
    }

    /**
     * Prints the statistics as JSON to stdout.
     */
    public function json( $useCache = FALSE )
    {
        Message::send(
            new StatsMessage(
                $this->activeFolder,
                (bool) $this->asleep,
                $this->activeAccount,
                (bool) $this->running,
                time() - $this->startTime,
                $this->getStats( $useCache )
            ));
    }
}