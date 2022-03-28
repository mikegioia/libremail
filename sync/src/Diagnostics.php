<?php

namespace App;

use App\Exceptions\AttachmentsPathNotWriteable as AttachmentsPathNotWriteableException;
use App\Exceptions\Fatal as FatalException;
use App\Exceptions\LogPathNotWriteable as LogPathNotWriteableException;
use App\Exceptions\MaxAllowedPacket as MaxAllowedPacketException;
use App\Exceptions\Terminate as TerminateException;
use App\Message\DiagnosticsMessage;
use App\Message\ErrorMessage;
use App\Model\Account as AccountModel;
use App\Model\Migration as MigrationModel;
use App\Util;
use Exception;
use PDOException;
use Pimple\Container;

class Diagnostics
{
    private $cli;
    private $console;
    private $di;
    private $log;

    /**
     * Statically set config used in static methods.
     *
     * @var array
     */
    private static $config;

    /**
     * Stores tests, their error messages, and statuses.
     *
     * @var array
     */
    private $tests;

    /**
     * Test name constants.
     */
    const TEST_DB_CONN = 'db_conn';
    const TEST_LOG_PATH = 'log_path';
    const TEST_DB_EXISTS = 'db_exists';
    const TEST_MAX_PACKET = 'max_packet';
    const TEST_ATTACH_PATH = 'attach_path';

    public function __construct(Container $di)
    {
        $this->di = $di;
        $this->cli = $di['cli'];
        $this->console = $di['console'];
        $this->log = $di['log']->getLogger();
        $this->tests = [
            self::TEST_LOG_PATH => [
                'code' => 1,
                'status' => null,
                'message' => null,
                'name' => 'log path is writable',
                'suggestion' => 'Check the permissions on the logging directory.'
            ],
            self::TEST_DB_CONN => [
                'code' => 2,
                'status' => null,
                'message' => null,
                'name' => 'database connection is alive',
                'suggestion' => 'Make sure your SQL username and password are correct and '.
                    'that you have a SQL database running.'
            ],
            self::TEST_DB_EXISTS => [
                'code' => 3,
                'status' => null,
                'message' => null,
                'name' => 'database was created',
                'suggestion' => 'You probably need to create the SQL database.'
            ],
            self::TEST_MAX_PACKET => [
                'code' => 4,
                'status' => null,
                'message' => null,
                'name' => 'max_allowed_packet is safe',
                'suggestion' => 'Update your max_allowed_packet in your SQL config file.'
            ],
            self::TEST_ATTACH_PATH => [
                'code' => 5,
                'status' => null,
                'message' => null,
                'name' => 'attachment path is writable',
                'suggestion' => 'Check the permissions on the attachment directory. This '.
                    'directory is needed to save file attachments from your emails.'
            ]
        ];

        // Set the config statically
        self::$config = $di['config'];
    }

    /**
     * Runs a series of diagnostic checks like writing to the
     * log file, connecting to the database, checking the path
     * to save attachments is writeable, and others.
     */
    public function run()
    {
        if ($this->console->databaseExists) {
            $this->runDatabaseTests(); // exits
        }

        $this->start();
        $this->testLogPathWritable();
        $this->testDatabaseConnection();
        $this->testDatabaseExists();
        $this->testMaxAllowedPacketSize();
        $this->testAttachmentPathWritable();
        $this->finish();

        if ($this->console->diagnostics) {
            exit($this->hasError() ? 1 : 0);
        }
    }

    /**
     * Checks only of the database exists.
     * Exit codes:
     *   0: if OK
     *   1: if database connection is down
     *   2: if database doesn't exist
     */
    public function runDatabaseTests()
    {
        $this->testDatabaseConnection();
        $this->testDatabaseExists();

        if (STATUS_ERROR === $this->tests[self::TEST_DB_CONN]['status']) {
            if ($this->console->interactive) {
                $this->cli->red($this->tests[self::TEST_DB_CONN]['message']);
            }

            exit(1);
        }

        if (STATUS_ERROR === $this->tests[self::TEST_DB_EXISTS]['status']) {
            if ($this->console->interactive) {
                $this->cli->red($this->tests[self::TEST_DB_EXISTS]['message']);
            }

            exit(2);
        }

        if ($this->console->interactive) {
            $this->cli->green('Database tests passsed!');
        }

        exit(0);
    }

    /**
     * No-op for container to load this service.
     */
    public function init()
    {
        // does nothing
    }

    /**
     * Check if the log path is writable.
     */
    public function testLogPathWritable()
    {
        $this->startTest(self::TEST_LOG_PATH);

        try {
            $path = Log::preparePath(self::$config['log']['path']);
            Log::checkLogPath(false, $path);
            $this->endTest(STATUS_SUCCESS, self::TEST_LOG_PATH);
        } catch (LogPathNotWriteableException $e) {
            $this->endTest(STATUS_ERROR, self::TEST_LOG_PATH, $e);
        }
    }

    /**
     * Try to connect to the database.
     */
    public function testDatabaseConnection()
    {
        $this->startTest(self::TEST_DB_CONN);

        try {
            $dbConfig = self::$config['sql'];
            $dbConfig['database'] = '';
            $dbFactory = $this->di->raw('db_factory');
            $db = $dbFactory($this->di, $dbConfig);
            $this->endTest(STATUS_SUCCESS, self::TEST_DB_CONN);
        } catch (TerminateException $e) {
            $this->endTest(STATUS_ERROR, self::TEST_DB_CONN, $e);
        }

        $db = null;
        unset($db);
    }

    /**
     * Check if the database actually exists.
     */
    public function testDatabaseExists()
    {
        $this->startTest(self::TEST_DB_EXISTS);

        try {
            $db = $this->di['db_factory'];
            $this->endTest(STATUS_SUCCESS, self::TEST_DB_EXISTS);
        } catch (TerminateException $e) {
            $this->endTest(STATUS_ERROR, self::TEST_DB_EXISTS, $e);
        }

        $db = null;
        unset($db);
    }

    /**
     * Check if the SQL max_allowed_packet is at a safe level.
     */
    public function testMaxAllowedPacketSize()
    {
        $this->startTest(self::TEST_MAX_PACKET);

        try {
            $safeMb = 16;
            $db = $this->di['db_factory'];
            $migrationModel = new MigrationModel;
            $safeSize = (int) ($safeMb * 1024 * 1024);
            $size = $migrationModel->getMaxAllowedPacket($db);

            if (! $size || $size < $safeSize) {
                $e = new MaxAllowedPacketException(
                    Util::formatBytes($size, 0),
                    Util::formatBytes($safeSize, 0)
                );

                $this->endTest(STATUS_WARNING, self::TEST_MAX_PACKET, $e);
            } else {
                $this->endTest(STATUS_SUCCESS, self::TEST_MAX_PACKET);
            }
        } catch (TerminateException $e) {
            $this->endTest(STATUS_SKIP, self::TEST_MAX_PACKET);
        } catch (PDOException $e) {
            $this->endTest(STATUS_ERROR, self::TEST_MAX_PACKET, $e);
        }

        $db = null;
        unset($db);
    }

    /**
     * Check if the attachment path is writable.
     */
    public function testAttachmentPathWritable()
    {
        $this->startTest(self::TEST_ATTACH_PATH);

        try {
            $attachmentPath = self::checkAttachmentsPath(
                'test@example.org',
                false // don't create the directory
            );

            $this->endTest(STATUS_SUCCESS, self::TEST_ATTACH_PATH);
        } catch (AttachmentsPathNotWriteableException $e) {
            $this->endTest(STATUS_ERROR, self::TEST_ATTACH_PATH, $e);
        }
    }

    /**
     * Returns the internal testing info.
     *
     * @return array
     */
    public function getTests()
    {
        return $this->tests;
    }

    /**
     * Check if any errors were hit during the testing.
     *
     * @return bool
     */
    public function hasError()
    {
        foreach ($this->tests as $test => $data) {
            if (STATUS_ERROR === $data['status']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle any output before the tests have started.
     */
    private function start()
    {
        if (! $this->console->diagnostics) {
            return;
        }

        $this->cli->info('Starting diagnostic tests');

        if ($this->hasError()) {
            $this->cli->comment(
                'There were errors encountered during the tests that '.
                'prevent this application from running!'
            );
        }
    }

    /**
     * Mark test as started and possibly write to console.
     *
     * @param const $test
     */
    private function startTest(string $test)
    {
        $message = $this->tests[$test]['name'];

        if ($this->console->diagnostics) {
            $this->cli->inline("[....] Testing $message");
        } elseif (! $this->console->interactive) {
            $this->log->addDebug("[Diagnostics] Testing $message");
        }
    }

    /**
     * Mark test as finished and write to log and/or console.
     *
     * @param string $status
     * @param const $test
     * @param Exception $e Optional exception for errors
     *
     * @throws FatalException
     */
    private function endTest(string $status, string $test, Exception $e = null)
    {
        $code = $this->tests[$test]['code'];
        $message = $e
            ? $e->getMessage()
            : 'Testing '.$this->tests[$test]['name'];
        $this->tests[$test]['status'] = $status;
        $this->tests[$test]['message'] = $message;

        // Use exceptions if we're not in a diagnostic or daemon mode
        if (! $this->console->diagnostics) {
            if (STATUS_ERROR === $status
                && ! $this->console->daemon
                && ! $this->console->databaseExists
            ) {
                throw new FatalException(
                    "Failed diagnostic test #$code, $message"
                );
            }

            if (! $this->console->interactive) {
                $this->log->addDebug('[Diagnostics] Finished with status '.$status);
            }

            return;
        }

        if (STATUS_SKIP === $status) {
            $this->cli->dim("\r[skip] $message");

            return;
        }

        $this->cli->inline("\r[");

        switch ($status) {
            case STATUS_WARNING:
                $this->cli->yellowInline('warn')->inline('] ');
                $this->cli->yellow($message);
                break;
            case STATUS_ERROR:
                $this->cli->redInline('fail')->inline('] ');
                $this->cli->red($message);
                break;
            case STATUS_SUCCESS:
                $this->cli->greenInline(' ok ')->inline('] ');
                $this->cli->dim($message);
                break;
        }
    }

    /**
     * Handle any output after the tests have finished.
     */
    private function finish()
    {
        if ($this->console->daemon) {
            Message::send(
                new DiagnosticsMessage(
                    $this->tests
                ));
        }

        if (! $this->console->diagnostics) {
            return;
        }

        if ($this->hasError()) {
            $this->cli->br()->boldRedBackgroundBlack(
                'There were errors encountered during the tests that '.
                'prevent this application from running!'
            );
        }
    }

    /**
     * Attempts to connect to the mail server using the new account
     * settings from the prompt.
     *
     * @param array $account Account credentials
     *
     * @throws Exception
     */
    public static function testImapConnection(array $account)
    {
        $sync = new Sync;
        $sync->setConfig(self::$config);
        $sync->connect(new AccountModel([
            'email' => $account['email'],
            'password' => $account['password'],
            'imap_host' => $account['imap_host'],
            'imap_port' => $account['imap_port']
        ]), false);
    }

    /**
     * Checks if the attachments path is writeable by the user.
     *
     * @param string $email
     * @param bool $createEmailDir
     *
     * @throws AttachmentsPathNotWriteableException
     *
     * @return bool
     */
    public static function checkAttachmentsPath(string $email, bool $createEmailDir = true)
    {
        $slash = DIRECTORY_SEPARATOR;
        $configPath = self::$config['email']['attachments']['path'];
        $attachmentsDir = substr($configPath, 0, 1) !== $slash
            ? BASEPATH
            : $configPath;

        if (! is_writeable($attachmentsDir)) {
            throw new AttachmentsPathNotWriteableException($attachmentsDir);
        }

        if (! $createEmailDir) {
            return true;
        }

        $attachmentsPath = substr($configPath, 0, 1) !== $slash
            ? BASEPATH."$slash$configPath"
            : $configPath;
        $attachmentsPath .= "$slash$email";

        @mkdir($attachmentsPath, 0755, true);

        return $attachmentsPath;
    }

    /**
     * Reads in a PDOException and checks if it's the SQL server
     * going away. If so, disconnect from the database and attempt
     * to reconnect.
     *
     * @param Container $di Dependency container
     * @param PDOException $e The recently thrown exception
     * @param bool $forwardException On failed re-connect, forward the
     *   termination exception
     * @param int $sleepSeconds Number of seconds to halt before
     *   creating the new database connection
     *
     * @throws TerminateException if re-connect fails and the flag
     *   $forwardException is true
     */
    public static function checkDatabaseException(
        Container &$di,
        PDOException $e,
        bool $forwardException = false,
        bool $sleepSeconds = null
    ) {
        $messages = [
            'Lost connection',
            'Error while sending',
            'server has gone away',
            'is dead or not enabled',
            'no connection to the server',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly'
        ];

        if (! Util::contains($e->getMessage(), $messages)) {
            if ($di['console']->daemon) {
                Message::send(
                    new ErrorMessage(
                        ERR_DATABASE,
                        $e->getMessage(),
                        'This could be a timeout problem, and if so the '.
                        'server is restarting itself.'
                    ));
            } else {
                $di['log']->getLogger()->addNotice($e->getMessage());
            }

            throw new TerminateException(
                'System encountered an un-recoverable database error. '.
                'Going to halt now, please see the log file for info.');
        } else {
            $di['log']->getLogger()->addDebug(
                'Database connection lost: '.$e->getMessage()
            );
        }

        // This should drop the DB connection
        $di['db'] = null;

        if (is_numeric($sleepSeconds)) {
            $di['log']->getLogger()->addDebug(
                "Sleeping $sleepSeconds before attempting to re-connect.");
            sleep($sleepSeconds);
        }

        try {
            // Create a new database connection. This will throw a
            // TerminateException on failure to connect.
            $di['db'] = $di['db_factory'];
            Model::setDb($di['db']);
        } catch (TerminateException $err) {
            if ($forwardException) {
                throw $err;
            }
        }
    }
}
