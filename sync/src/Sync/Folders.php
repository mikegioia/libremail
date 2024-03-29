<?php

namespace App\Sync;

use App\Model\Account as AccountModel;
use App\Model\Folder as FolderModel;
use App\Sync;
use App\Util;
use Evenement\EventEmitter as Emitter;
use League\CLImate\CLImate;
use Monolog\Logger;

/**
 * Syncs IMAP folders to SQL.
 */
class Folders
{
    private $log;
    private $cli;
    private $emitter;
    private $interactive;

    public const IGNORED_LIST = [
        '[Gmail]'
    ];

    public function __construct(
        Logger $log,
        CLImate $cli,
        Emitter $emitter,
        bool $interactive
    ) {
        $this->log = $log;
        $this->cli = $cli;
        $this->emitter = $emitter;
        $this->interactive = $interactive;
    }

    /**
     * Syncs a set of IMAP folders with what we have in SQL.
     *
     * @param array<FolderModel> $savedFolders
     */
    public function run(iterable $folderList, array $savedFolders, AccountModel $account)
    {
        $count = iterator_count($folderList);

        $this->log->debug("Found $count ".Util::plural('folder', $count));

        $this->addNewFolders($folderList, $savedFolders, $account);
        $this->removeOldFolders($folderList, $savedFolders, $account);
    }

    /**
     * Adds new folders from IMAP to the database.
     *
     * @param array<FolderModel> $savedFolders
     */
    private function addNewFolders(
        iterable $folderList,
        array $savedFolders,
        AccountModel $account
    ) {
        $i = 1;
        $toAdd = [];

        foreach ($folderList as $folderName) {
            if (! array_key_exists((string) $folderName, $savedFolders)) {
                $toAdd[] = (string) $folderName;
            }
        }

        if (! ($count = count($toAdd))) {
            $this->log->debug('No new folders to save');

            return;
        }

        if ($this->interactive) {
            $this->cli->whisper(
                "Adding $count new ".Util::plural('folder', $count).':'
            );
            $progress = $this->cli->progress()->total($count);
        } else {
            $this->log->info(
                "Adding $count new ".Util::plural('folder', $count)
            );
        }

        foreach ($toAdd as $folderName) {
            $folder = new FolderModel([
                'name' => $folderName,
                'account_id' => $account->getId(),
                'ignored' => $this->getIgnored($folderName)
            ]);

            $folder->save();
            $folders[$folder->getId()] = $folder;

            if ($this->interactive && isset($progress)) {
                $progress->current($i++);
            }

            $this->emitter->emit(Sync::EVENT_CHECK_HALT);
        }
    }

    /**
     * Removes purged folders no longer in the mailbox from the database.
     *
     * @param array<FolderModel> $savedFolders
     */
    private function removeOldFolders(
        iterable $folderList,
        array $savedFolders,
        AccountModel $account
    ) {
        $lookup = [];
        $toRemove = [];

        foreach ($folderList as $folderName) {
            $lookup[] = $folderName;
        }

        foreach ($savedFolders as $savedFolder) {
            if (! in_array($savedFolder->getName(), $lookup)) {
                $toRemove[] = $savedFolder;
            }
        }

        if (! ($count = count($toRemove))) {
            $this->log->debug('No folders to remove');

            return;
        }

        $this->log->info("Removing $count ".Util::plural('folder', $count));

        foreach ($toRemove as $folder) {
            $folder->delete();
        }
    }

    /**
     * Certain folders should be automatically ignored.
     *
     * @return int
     */
    private function getIgnored(string $folderName)
    {
        return in_array($folderName, self::IGNORED_LIST)
            ? 1
            : 0;
    }
}
