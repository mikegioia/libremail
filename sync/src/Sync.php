<?php

/**
 * Master class for handling all email sync activities.
 */

namespace App;

use Fn;
use DateTime;
use Exception;
use PDOException;
use Monolog\Logger;
use Pb\Imap\Mailbox;
use Pimple\Container;
use League\CLImate\CLImate;
use App\Message\NoAccountsMessage;
use App\Sync\Actions as ActionSync;
use App\Sync\Folders as FolderSync;
use App\Message\NotificationMessage;
use App\Model\Folder as FolderModel;
use App\Sync\Messages as MessageSync;
use App\Model\Account as AccountModel;
use Evenement\EventEmitter as Emitter;
use App\Exceptions\Stop as StopException;
use App\Model\Migration as MigrationModel;
use App\Exceptions\Fatal as FatalException;
use App\Exceptions\Terminate as TerminateException;
use App\Exceptions\FolderSync as FolderSyncException;
use App\Exceptions\MessagesSync as MessagesSyncException;
use App\Traits\GarbageCollection as GarbageCollectionTrait;
use App\Exceptions\MissingIMAPConfig as MissingIMAPConfigException;

class Sync
{
    private $cli;
    private $log;
    private $halt;
    private $stop;
    private $wake;
    private $once;
    private $sleep;
    private $quick;
    private $config;
    private $folder;
    private $daemon;
    private $asleep;
    private $actions;
    private $running;
    private $mailbox;
    private $retries;
    private $emitter;
    private $threader;
    private $threading;
    private $interactive;
    private $activeAccount;
    private $maxRetries = 5;
    private $retriesFolders;
    private $retriesMessages;

    // Options
    const OPT_SKIP_DOWNLOAD = 'skip_download';
    const OPT_ONLY_SYNC_ACTIONS = 'only_sync_actions';
    const OPT_ONLY_UPDATE_STATS = 'only_update_stats';

    // Events
    const EVENT_CHECK_HALT = 'check_halt';
    const EVENT_GARBAGE_COLLECT = 'garbage_collect';
    const EVENT_CHECK_CLOSED_CONN = 'check_closed_connection';

    use GarbageCollectionTrait;

    /**
     * Constructor can either take a dependency container or have
     * the dependencies loaded individually. The di method is
     * used when the sync app is run from a bootstrap file and the
     * ad hoc method is when this class is used separately within
     * other classes like Console.
     *
     * @param array $di Service container
     */
    public function __construct(Container $di = null)
    {
        $this->halt = false;
        $this->retries = [];
        $this->retriesFolders = [];
        $this->retriesMessages = [];

        if ($di) {
            $this->cli = $di['cli'];
            $this->stats = $di['stats'];
            $this->config = $di['config'];
            $this->threader = $di['threader'];
            $this->once = $di['console']->once;
            $this->log = $di['log']->getLogger();
            $this->quick = $di['console']->quick;
            $this->sleep = $di['console']->sleep;
            $this->folder = $di['console']->folder;
            $this->daemon = $di['console']->daemon;
            $this->actions = $di['console']->actions;
            $this->threading = $di['console']->threading;
            $this->interactive = $di['console']->interactive;
        }

        $this->initGc();
    }

    /**
     * @param CLImate $cli
     */
    public function setCLI(CLImate $cli)
    {
        $this->cli = $cli;
    }

    /**
     * @param Logger $log
     */
    public function setLog(Logger $log)
    {
        $this->log = $log;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Runs sync forever. This is a while loop that runs a sync
     * for all accounts, then sleeps for a designated period of
     * time.
     */
    public function loop()
    {
        $wakeUnix = 0;
        $sleepMinutes = $this->config['app']['sync']['sleep_minutes'];

        while (true) {
            $this->gc();
            $this->checkForHalt();
            $this->setRunning();

            if (true === $this->wake) {
                $wakeUnix = 0;
                $this->wake = false;
            }

            if ((new DateTime)->getTimestamp() < $wakeUnix) {
                // Run action sync every minute
                $this->run(null, [self::OPT_ONLY_SYNC_ACTIONS => true]);
                $this->setAsleep();
                sleep(60);
                continue;
            }

            $this->setAsleep(false);

            if (! $this->run()) {
                throw new TerminateException('Sync was prevented from running');
            }

            if (true === $this->once) {
                throw new TerminateException('Sync self-terminating after one run');
            }

            $wakeTime = Fn\timeFromNow($sleepMinutes);
            $wakeUnix = Fn\unixFromNow($sleepMinutes);

            $this->log->addInfo(
                "Going to sleep for $sleepMinutes minutes. Sync will ".
                "re-run at $wakeTime."
            );
        }
    }

    /**
     * For each account:
     *  1. Get the folders
     *  2. Save all message IDs for each folder
     *  3. For each folder, add/remove messages based off IDs
     *  4. Save attachments.
     *
     * @param AccountModel $account Optional account to run
     * @param array $options See valid options below
     *
     * @return bool
     */
    public function run(AccountModel $account = null, array $options = [])
    {
        if ($this->sleep) {
            return true;
        }

        if ($account) {
            $accounts = [$account];
        } else {
            $accountModel = new AccountModel;
            $accounts = $accountModel->getActive();
        }

        if (! $accounts) {
            $this->stats->setActiveAccount(null);

            // If we're in daemon mode, just go to sleep. The script
            // will pick up once the user creates an account and a
            // SIGCONT is sent to this process.
            if ($this->daemon) {
                Message::send(new NoAccountsMessage);

                return true;
            }

            $this->log->notice('No accounts to run, exiting.');

            return false;
        }

        // Try to set max allowed packet size in SQL
        $migration = new MigrationModel;

        if (! $migration->setMaxAllowedPacket(16)) {
            $this->log->notice(
                "The max_allowed_packet in MySQL is smaller than what's ".
                "safe for this sync. I've attempted to change it to 16 MB ".
                'but you should re-run this script to re-test. Please see '.
                'the documentation on updating this MySQL setting in your '.
                'configuration file.'
            );

            throw new Exception('Halting script');
        }

        // Loop through the active accounts and perform the sync
        // sequentially. The IMAP methods throw exceptions so want
        // to wrap this is a try/catch block.
        foreach ($accounts as $account) {
            $this->retries[$account->email] = 1;
            $this->runAccount($account, $options);
        }

        return true;
    }

    /**
     * Runs the sync script for an account. Each action (i.e. connecting
     * to the server, syncing folders, syncing messages, etc) should be
     * allowed to fail a certain number of times before the account is
     * considered offline.
     *
     * @param AccountModel $account
     * @param array $options Valid options include:
     *   only_update_stats (false) If true, only stats about the
     *     folders will be logged. Messages won't be downloaded.
     *   only_sync_actions (false) If true, only the pending actions
     *     will be synced to the IMAP server.
     *
     * @return bool
     */
    public function runAccount(AccountModel $account, array $options = [])
    {
        if ($this->retries[$account->email] > $this->maxRetries) {
            $message =
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries after failure ({$this->maxRetries}) ".
                'and is no longer being attempted to sync again.';
            $this->log->notice($message);
            $this->sendMessage($message);

            return false;
        }

        $this->setupEmitter();

        // If we're running in threading mode, just update threads
        if (true === $this->threading) {
            $this->log->info("Syncing threads for {$account->email}");
            $this->updateThreads($account);

            return true;
        }

        $this->checkForHalt();
        $this->stats->setActiveAccount($account->email);

        try {
            // Open a connection to the mailbox
            $this->disconnect();
            $this->connect(
                $account->imap_host,
                $account->imap_port,
                $account->email,
                $account->password
            );

            // Commit any pending actions to the mail server
            $this->syncActions($account);

            if (true === Fn\get($options, self::OPT_ONLY_SYNC_ACTIONS)
                || true === $this->actions
            ) {
                $this->disconnect();

                return true;
            }

            $this->log->info("Starting sync for {$account->email}");

            // Check if we're only syncing one folder
            if ($this->folder) {
                try {
                    $folderModel = new FolderModel;
                    $folder = $folderModel->getByName(
                        $account->getId(),
                        $this->folder,
                        $failOnNotFound = true
                    );
                } catch (PDOException $e) {
                    throw $e;
                } catch (Exception $e) {
                    throw new FatalException(
                        'Syncing that folder failed: '.$e->getMessage()
                    );
                }

                $this->syncMessages($account, [$folder]);
            } else {
                // Fetch folders and sync them to database
                $folderModel = new FolderModel;
                $this->retriesFolders[$account->email] = 1;
                $this->syncFolders($account);
                $folders = $folderModel->getByAccount($account->getId());
                // First pass, just log the message stats
                $this->syncMessages($account, $folders, [
                    self::OPT_SKIP_DOWNLOAD => true
                ]);

                // If the all that's requested is to update the folder stats,
                // then we can exit here.
                if (true === Fn\get($options, self::OPT_ONLY_UPDATE_STATS)) {
                    return;
                }

                // Second pass, download the messages. Yes, we could have stored
                // an array of folders with 0 messages (to skip) but if we're
                // going to run again in 15 minutes, why not just do two passes
                // and download any extra messages while we can?
                $this->syncMessages($account, $folders);
                // Disconnect the IMAP connection, but leave the running flag on
                $this->disconnect(true);
            }
        } catch (PDOException $e) {
            throw $e;
        } catch (FatalException $e) {
            $this->log->critical($e->getMessage());
            exit(1);
        } catch (StopException $e) {
            throw $e;
        } catch (TerminateException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
            $this->checkForClosedConnection($e);
            $waitSeconds = $this->config['app']['sync']['wait_seconds'];
            $this->log->info(
                "Re-trying sync ({$this->retries[$account->email]}/".
                "{$this->maxRetries}) in $waitSeconds seconds...");
            sleep($waitSeconds);
            ++$this->retries[$account->email];

            return $this->runAccount($account);
        }

        $this->disconnect();
        $this->log->info("Sync complete for {$account->email}");

        return true;
    }

    /**
     * Connects to an IMAP mailbox using the supplied credentials.
     *
     * @param string $host IMAP hostname, like 'imap.host.com'
     * @param string $port IMAP port, usually 993 (not used)
     * @param string $email
     * @param string $password
     * @param string $folder Optional, like "INBOX"
     * @param bool $setRunning Optional
     *
     * @throws MissingIMAPConfigException
     */
    public function connect(
        string $host,
        string $port,
        string $email,
        string $password,
        string $folder = null,
        bool $setRunning = true
    ) {
        // Check the attachment directory is writeable
        $attachmentsPath = Diagnostics::checkAttachmentsPath($email);

        // If the connection is active, then just select the folder
        if ($this->mailbox) {
            $this->mailbox->select($folder);

            return;
        }

        // Add connection settings and attempt the connection
        $this->mailbox = new Mailbox(
            $host,
            $email,
            $password,
            $folder ?: '',
            $attachmentsPath, [
                Mailbox::OPT_SKIP_ATTACHMENTS => $this->quick
            ]);
        $this->mailbox->getImapStream();

        if (true === $setRunning) {
            $this->setRunning(true);
        }
    }

    public function disconnect(bool $running = false)
    {
        if ($this->mailbox) {
            try {
                $this->mailbox->disconnect();
            } catch (Exception $e) {
                $this->checkForClosedConnection($e);

                throw $e;
            }
        }

        $this->mailbox = null;
        $this->setAsleep(false);
        $this->setRunning($running);
    }

    public function setAsleep(bool $asleep = true)
    {
        $this->asleep = $asleep;
        $this->stats->setAsleep($asleep);
    }

    public function setRunning(bool $running = true)
    {
        $this->running = $running;
        $this->stats->setRunning($running);
    }

    /**
     * Turns the halt flag on. Message sync operations check for this
     * and throw a TerminateException if true.
     */
    public function halt()
    {
        $this->halt = true;

        // If we're sleeping forever, throw the exception now
        if (true === $this->sleep) {
            throw new TerminateException;
        }
    }

    public function stop()
    {
        $this->halt = true;
        $this->stop = true;
    }

    public function wake()
    {
        $this->wake = true;
        $this->halt = false;
    }

    /**
     * Attaches events to emitter for sub-classes.
     */
    private function setupEmitter()
    {
        if ($this->emitter) {
            return;
        }

        $this->emitter = new Emitter;

        $this->emitter->on(self::EVENT_CHECK_HALT, function () {
            $this->checkForHalt();
        });

        $this->emitter->on(self::EVENT_GARBAGE_COLLECT, function () {
            $this->gc();
        });

        $this->emitter->on(self::EVENT_CHECK_CLOSED_CONN, function ($e) {
            $this->checkForClosedConnection($e);
        });
    }

    /**
     * Syncs a collection of IMAP folders to the database.
     *
     * @param AccountModel $account Account to sync
     *
     * @throws FolderSyncException
     *
     * @return array $folders List of IMAP folders
     */
    private function syncFolders(AccountModel $account)
    {
        if ($this->retriesFolders[$account->email] > $this->maxRetries) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                'amount of retries after folder sync failure '.
                "({$this->maxRetries})."
            );

            // @TODO increment a counter on the folder record
            // if its >=3 then mark folder as inactive
            throw new FolderSyncException;
        }

        $this->log->debug("Syncing IMAP folders for {$account->email}");

        try {
            $folderSync = new FolderSync(
                $this->log,
                $this->cli,
                $this->emitter,
                $this->interactive);
            $folderList = $this->mailbox->getFolders();
            $savedFolders = (new FolderModel)->getByAccount($account->getId());
            $folderSync->run($folderList, $savedFolders, $account);
        } catch (PDOException $e) {
            throw $e;
        } catch (StopException $e) {
            throw $e;
        } catch (TerminateException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
            $this->checkForClosedConnection($e);
            $waitSeconds = $this->config['app']['sync']['wait_seconds'];
            $this->log->info(
                "Re-trying folder sync ({$this->retriesFolders[$account->email]}/".
                "{$this->maxRetries}) in $waitSeconds seconds...");
            sleep($waitSeconds);
            ++$this->retriesFolders[$account->email];
            $this->checkForHalt();
            $this->syncFolders($account);
        }
    }

    /**
     * Syncs all of the messages for an account. This is set up
     * to try each folder some amount of times before moving on
     * to the next folder.
     *
     * @param AccountModel $account
     * @param array FolderModel $folders
     * @param array $options Valid options include:
     *   skip_download (false) If true, only stats about the
     *     folder will be logged. The messages won't be downloaded.
     */
    private function syncMessages(AccountModel $account, array $folders, array $options = [])
    {
        if (true === Fn\get($options, self::OPT_SKIP_DOWNLOAD)) {
            $this->log->debug('Updating folder counts');
        } else {
            $this->log->debug('Syncing messages in each folder');
        }

        foreach ($folders as $folder) {
            $this->retriesMessages[$account->email] = 1;
            $this->stats->setActiveFolder($folder->name);

            try {
                $this->syncFolderMessages($account, $folder, $options);
            } catch (MessagesSyncException $e) {
                $this->log->error($e->getMessage());
            }

            $this->checkForHalt();
            $this->updateThreads($account);
            $this->checkForHalt();
        }

        $this->stats->unsetActiveFolder();
    }

    /**
     * Updates message threads. See Threading class for info.
     * This will run for a long time for the first iteration,
     * and all subsequent runs will only update threads for
     * new messages.
     *
     * @param AccountModel $account
     */
    private function updateThreads(AccountModel $account)
    {
        $this->threader->run($account, $this->emitter);
    }

    /**
     * Syncs all of the messages for a given IMAP folder.
     *
     * @param AccountModel $account
     * @param FolderModel $folder
     * @param array $options (see syncMessages)
     *
     * @throws MessagesSyncException
     *
     * @return bool
     */
    private function syncFolderMessages(
        AccountModel $account,
        FolderModel $folder,
        array $options
    ) {
        if ($folder->isIgnored()) {
            $this->log->debug('Skipping ignored folder');

            return;
        }

        if ($this->retriesMessages[$account->email] > $this->maxRetries) {
            $this->log->notice(
                "The account '{$account->email}' has exceeded the max ".
                "amount of retries ({$this->maxRetries}) after trying ".
                "to sync the folder '{$folder->name}'. Skipping to the ".
                'next folder.');

            throw new MessagesSyncException($folder->name);
        }

        $this->log->debug(
            "Syncing messages in {$folder->name} for {$account->email}");
        $this->log->debug(
            'Memory usage: '.Fn\formatBytes(memory_get_usage()).
            ', real usage: '.Fn\formatBytes(memory_get_usage(true)).
            ', peak usage: '.Fn\formatBytes(memory_get_peak_usage()));

        // Syncing a folder of messages is done using the following
        // algorithm:
        //  1. Get all message IDs
        //  2. Get all message IDs saved in SQL
        //  3. For anything in 1 and not 2, download messages and save
        //     to SQL database
        //  4. Mark deleted in SQL anything in 2 and not 1
        try {
            $messageSync = new MessageSync(
                $this->log,
                $this->cli,
                $this->emitter,
                $this->mailbox,
                $this->interactive, [
                    MessageSync::OPT_SKIP_CONTENT => $this->quick
                ]);
            // Select the folder's mailbox, this is sent to the
            // messages sync library to perform operations on
            $this->mailbox->select($folder->name);
            $messageSync->run($account, $folder, $options);
            $this->checkForHalt();
        } catch (PDOException $e) {
            throw $e;
        } catch (StopException $e) {
            throw $e;
        } catch (TerminateException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->stats->unsetActiveFolder();
            $this->log->error(substr($e->getMessage(), 0, 500));
            $this->checkForClosedConnection($e);
            $retryCount = $this->retriesMessages[$account->email];
            $waitSeconds = $this->config['app']['sync']['wait_seconds'];
            $this->log->info(
                "Re-trying message sync ($retryCount/{$this->maxRetries}) ".
                "for folder '{$folder->name}' in $waitSeconds seconds...");
            sleep($waitSeconds);
            ++$this->retriesMessages[$account->email];
            $this->checkForHalt();

            return $this->syncFolderMessages($account, $folder, $options);
        }
    }

    /**
     * Runs task sync engine to sync any local actions with the
     * server. This should be run before any message/folder sync.
     */
    private function syncActions(AccountModel $account)
    {
        (new ActionSync(
            $this->log,
            $this->cli,
            $this->emitter,
            $this->mailbox,
            $this->interactive
        ))->run($account);

        $this->checkForHalt();
    }

    private function sendMessage(string $message, string $status = STATUS_ERROR)
    {
        if ($this->daemon) {
            Message::send(new NotificationMessage($status, $message));
        }
    }

    /**
     * Checks if a halt command has been issued. This is a command
     * to stop the sync. We want to do is gracefull though so the
     * app checks in various places when it's save to halt.
     *
     * @throws StopException
     * @throws TerminateException
     */
    private function checkForHalt()
    {
        pcntl_signal_dispatch();

        if (true === $this->halt) {
            $this->disconnect();
            $this->stats->setActiveAccount(null);

            // If there was a stop command issued, then don't terminate
            if (true === $this->stop) {
                throw new StopException;
            }

            // If we just want to sleep, then don't terminate
            if (true !== $this->sleep) {
                throw new TerminateException;
            }
        }
    }

    /**
     * Checks the exception message for a "closed connection" string.
     * This can happen when the IMAP socket is closed or fails. When
     * this happens we want to terminate the sync and let the whole
     * thing pick back up.
     *
     * @param Exception $e
     *
     * @throws StopException
     */
    private function checkForClosedConnection(Exception $e)
    {
        if (false !== strpos($e->getMessage(), 'connection closed?')) {
            $this->sendMessage(
                'The IMAP connection was lost. Your internet connection '.
                'could be down or it could just be a network error. The '.
                'system will sleep for a bit before re-trying.',
                STATUS_ERROR
            );

            throw new StopException;
        }
    }
}
