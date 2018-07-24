<?php

namespace App;

use Fn;
use App\Message\StatsMessage;
use App\Model\Folder as FolderModel;
use App\Model\Account as AccountModel;

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

    public function __construct(Console $console)
    {
        $this->cli = $console->getCli();
        $this->daemon = $console->daemon;
    }

    public function setAsleep($asleep = true)
    {
        $this->asleep = $asleep;
        $this->unsetActiveFolder();
    }

    public function setRunning($running = true)
    {
        $this->running = $running;

        if ($running && ! $this->startTime) {
            $this->startTime = time();
        }
    }

    public function setActiveAccount($account)
    {
        $this->activeAccount = $account;

        if ($this->daemon) {
            $this->json(true);
        }
    }

    public function setActiveFolder($folder)
    {
        $this->activeFolder = $folder;

        if ($this->daemon) {
            $this->json();
        }
    }

    public function unsetActiveFolder()
    {
        $this->activeFolder = null;

        if ($this->daemon) {
            $this->json();
        }
    }

    /**
     * Returns all of the statistics and sync status info on
     * all of the folders for all of the accounts.
     *
     * @param array $useCache If true, use it instead of fetching.
     *   This is useful when sending the message to the client
     *   like 'update the active folder' but without needing to
     *   query for all folder statistics.
     *
     * @return array
     */
    public function getStats($useCache = false)
    {
        if (true === $useCache && ! is_null($this->stats)) {
            return $this->stats;
        }

        // Get all of the accounts. For each, get all of the
        // folders and their statistics info. Build this into
        // an multi-array.
        $stats = [];
        $folderModel = new FolderModel;
        $accountModel = new AccountModel;
        $accounts = $accountModel->getActive();

        foreach ($accounts as $account) {
            $folderStats = [];
            $folders = $folderModel->getByAccount($account->getId());

            foreach ($folders as $folder) {
                if ($folder->isIgnored()) {
                    continue;
                }

                $folderStats[$folder->getName()] = [
                    'count' => $folder->getCount(),
                    'synced' => $folder->getSynced(),
                    'percent' => ($folder->getSynced() > 0)
                        ? Fn\percent($folder->getSynced() / $folder->getCount())
                        : (($folder->getCount() > 0)
                            ? '0%'
                            : '100%')
                ];
            }

            $stats[$account->getEmail()] = $folderStats;
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

        foreach ($stats as $account => $folders) {
            $columns = [
                ['Folder', 'Count', 'Synced', '%']
            ];
            $this->cli->out($account);

            foreach ($folders as $folderName => $folderStats) {
                $columns[] = [
                    $folderName,
                    $folderStats['count'],
                    $folderStats['synced'],
                    $folderStats['percent']
                ];
            }

            $this->cli->columns($columns);
        }
    }

    /**
     * Prints the statistics as JSON to stdout.
     */
    public function json($useCache = false)
    {
        Message::send(
            new StatsMessage(
                $this->activeFolder,
                (bool) $this->asleep,
                $this->activeAccount,
                (bool) $this->running,
                time() - $this->startTime,
                $this->getStats($useCache)
            ));
    }
}
