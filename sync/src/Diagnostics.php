<?php

namespace App;

use Fn
  , App\Log
  , App\Sync
  , Exception
  , App\Daemon
  , App\Message
  , PDOException
  , Pimple\Container
  , League\CLImate\CLImate
  , App\Message\ErrorMessage
  , App\Message\DiagnosticsMessage
  , App\Model\Migration as MigrationModel
  , App\Exceptions\Fatal as FatalException
  , App\Exceptions\Terminate as TerminateException
  , App\Exceptions\MaxAllowedPacket as MaxAllowedPacketException
  , App\Exceptions\LogPathNotWriteable as LogPathNotWriteableException
  , App\Exceptions\AttachmentsPathNotWriteable as AttachmentsPathNotWriteableException;

class Diagnostics
{
    private $di;
    private $cli;
    private $log;
    private $console;

    /**
     * Statically set config used in static methods.
     * @var array
     */
    static private $config;

    /**
     * Stores tests, their error messages, and statuses.
     * @var array
     */
    private $tests;

    /**
     * Test name constants
     */
    const TEST_DB_CONN = 'db_conn';
    const TEST_LOG_PATH = 'log_path';
    const TEST_DB_EXISTS = 'db_exists';
    const TEST_MAX_PACKET = 'max_packet';
    const TEST_ATTACH_PATH = 'attach_path';

    public function __construct( Container $di )
    {
        $this->di = $di;
        $this->cli = $di[ 'cli' ];
        $this->console = $di[ 'console' ];
        $this->log = $di[ 'log' ]->getLogger();
        $this->tests = [
            self::TEST_LOG_PATH => [
                'code' => 1,
                'status' => NULL,
                'message' => NULL,
                'name' => 'log path is writable',
                'suggestion' =>
                    'Check the permissions on the logging directory.'
            ],
            self::TEST_DB_CONN => [
                'code' => 2,
                'status' => NULL,
                'message' => NULL,
                'name' => 'database connection is alive',
                'suggestion' =>
                    'Make sure your SQL username and password are correct and '.
                    'that you have a SQL database running.'
            ],
            self::TEST_DB_EXISTS => [
                'code' => 3,
                'status' => NULL,
                'message' => NULL,
                'name' => 'database was created',
                'suggestion' => 'You probably need to create the SQL database.'
            ],
            self::TEST_MAX_PACKET => [
                'code' => 4,
                'status' => NULL,
                'message' => NULL,
                'name' => 'max_allowed_packet is safe',
                'suggestion' =>
                    'Update your max_allowed_packet in your SQL config file.'
            ],
            self::TEST_ATTACH_PATH => [
                'code' => 5,
                'status' => NULL,
                'message' => NULL,
                'name' => 'attachment path is writable',
                'suggestion' =>
                    'Check the permissions on the attachment directory. This '.
                    'directory is needed to save file attachments from your emails.'
            ]
        ];

        // Set the config statically
        self::$config = $di[ 'config' ];
    }

    /**
     * Runs a series of diagnostic checks like writing to the
     * log file, connecting to the database, checking the path
     * to save attachments is writeable, and others.
     */
    public function run()
    {
        $this->start();
        $this->testLogPathWritable();
        $this->testDatabaseConnection();
        $this->testDatabaseExists();
        $this->testMaxAllowedPacketSize();
        $this->testAttachmentPathWritable();
        $this->finish();

        if ( $this->console->diagnostics ) {
            exit( 0 );
        }
    }

    /**
     * No-op used to instantiate class if we're not running this
     * on startup.
     */
    public function init() {}

    /**
     * Check if the log path is writable.
     */
    public function testLogPathWritable()
    {
        $this->startTest( self::TEST_LOG_PATH );

        try {
            $path = Log::preparePath( self::$config[ 'log' ][ 'path' ] );
            Log::checkLogPath( FALSE, $path );
            $this->endTest( STATUS_SUCCESS, self::TEST_LOG_PATH );
        }
        catch ( LogPathNotWriteableException $e ) {
            $this->endTest( STATUS_ERROR, self::TEST_LOG_PATH, $e );
        }
    }

    /**
     * Try to connect to the database.
     */
    public function testDatabaseConnection()
    {
        $this->startTest( self::TEST_DB_CONN );

        try {
            $dbConfig = self::$config[ 'sql' ];
            $dbConfig[ 'database' ] = '';
            $dbFactory = $this->di->raw( 'db_factory' );
            $db = $dbFactory( $this->di, $dbConfig );
            $this->endTest( STATUS_SUCCESS, self::TEST_DB_CONN );
        }
        catch ( TerminateException $e ) {
            $this->endTest( STATUS_ERROR, self::TEST_DB_CONN, $e );
        }

        $db = NULL;
        unset( $db );
    }

    /**
     * Check if the database actually exists.
     */
    public function testDatabaseExists()
    {
        $this->startTest( self::TEST_DB_EXISTS );

        try {
            $db = $this->di[ 'db_factory' ];
            $this->endTest( STATUS_SUCCESS, self::TEST_DB_EXISTS );
        }
        catch ( TerminateException $e ) {
            $this->endTest( STATUS_ERROR, self::TEST_DB_EXISTS, $e );
        }

        $db = NULL;
        unset( $db );
    }

    /**
     * Check if the SQL max_allowed_packet is at a safe level.
     */
    public function testMaxAllowedPacketSize()
    {
        $this->startTest( self::TEST_MAX_PACKET );

        try {
            $safeMb = 16;
            $db = $this->di[ 'db_factory' ];
            $migrationModel = new MigrationModel;
            $safeSize = (int) ( $safeMb * 1024 * 1024 );
            $size = $migrationModel->getMaxAllowedPacket( $db );

            if ( ! $size || $size < $safeSize ) {
                $e = new MaxAllowedPacketException(
                    Fn\formatBytes( $size, 0 ),
                    Fn\formatBytes( $safeSize, 0 ) );
                $this->endTest( STATUS_WARNING, self::TEST_MAX_PACKET, $e );
            }
            else {
                $this->endTest( STATUS_SUCCESS, self::TEST_MAX_PACKET );
            }
        }
        catch ( TerminateException $e ) {
            $this->endTest( STATUS_SKIP, self::TEST_MAX_PACKET );
        }
        catch ( PDOException $e ) {
            $this->endTest( STATUS_ERROR, self::TEST_MAX_PACKET, $e );
        }

        $db = NULL;
        unset( $db );
    }

    /**
     * Check if the attachment path is writable.
     */
    public function testAttachmentPathWritable()
    {
        $this->startTest( self::TEST_ATTACH_PATH );

        try {
            $sync = new Sync( $this->di );
            $attachmentPath = $sync->checkAttachmentsPath(
                'test@example.org',
                FALSE ); // don't create the directory
            $this->endTest( STATUS_SUCCESS, self::TEST_ATTACH_PATH );
        }
        catch ( AttachmentsPathNotWriteableException $e ) {
            $this->endTest( STATUS_ERROR, self::TEST_ATTACH_PATH, $e );
        }
    }

    /**
     * Returns the internal testing info.
     * @return array
     */
    public function getTests()
    {
        return $this->tests;
    }

    /**
     * Check if any errors were hit during the testing.
     * @return bool
     */
    public function hasError()
    {
        foreach ( $this->tests as $test => $data ) {
            if ( $data[ 'status' ] === STATUS_ERROR ) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Handle any output before the tests have started.
     */
    private function start()
    {
        if ( ! $this->console->diagnostics ) {
            return;
        }

        $this->cli->info( "Starting diagnostic tests" );

        if ( $this->hasError() ) {
            $this->cli->comment(
                "There were errors encountered during the tests that ".
                "prevent this application from running!" );
        }
    }

    /**
     * Mark test as started and possibly write to console.
     * @param const $test
     */
    private function startTest( $test )
    {
        $message = $this->tests[ $test ][ 'name' ];

        if ( $this->console->diagnostics ) {
            $this->cli->inline( "[....] Testing $message" );
        }
        elseif ( ! $this->console->interactive ) {
            $this->log->addDebug( "[Diagnostics] Testing $message" );
        }
    }

    /**
     * Mark test as finished and write to log and/or console.
     * @param string $status
     * @param const $test
     * @param Exception $e Optional exception for errors
     * @throws FatalException
     */
    private function endTest( $status, $test, Exception $e = NULL )
    {
        $code = $this->tests[ $test ][ 'code' ];
        $message = ( $e )
            ? $e->getMessage()
            : "Testing ". $this->tests[ $test ][ 'name' ];
        $this->tests[ $test ][ 'status' ] = $status;
        $this->tests[ $test ][ 'message' ] = $message;

        // If we're not in diagnostic mode (and not daemon mode), then
        // use exceptions
        if ( ! $this->console->diagnostics ) {
            if ( $status === STATUS_ERROR && ! $this->console->daemon ) {
                throw new FatalException(
                    "Failed diagnostic test #$code, $message" );
            }

            if ( ! $this->console->interactive ) {
                $this->log->addDebug( "[Diagnostics] Finished with status ". $status );
            }

            return;
        }

        if ( $status === STATUS_SKIP ) {
            $this->cli->dim( "\r[skip] $message" );
            return;
        }

        $this->cli->inline( "\r[" );

        switch ( $status ) {
            case STATUS_WARNING:
                $this->cli->yellowInline( "warn" )->inline( "] " );
                $this->cli->yellow( $message );
                break;
            case STATUS_ERROR:
                $this->cli->redInline( "fail" )->inline( "] " );
                $this->cli->red( $message );
                break;
            case STATUS_SUCCESS:
                $this->cli->greenInline( " ok " )->inline( "] " );
                $this->cli->dim( $message );
                break;
        }
    }

    /**
     * Handle any output after the tests have finished.
     */
    private function finish()
    {
        if ( $this->console->daemon ) {
            Message::send(
                new DiagnosticsMessage(
                    $this->tests
                ));
        }

        if ( ! $this->console->diagnostics ) {
            return;
        }

        if ( $this->hasError() ) {
            $this->cli->br()->boldRedBackgroundBlack(
                "There were errors encountered during the tests that ".
                "prevent this application from running!" );
        }
    }

    /**
     * Attempts to connect to the mail server using the new account
     * settings from the prompt.
     * @param Array $account Account credentials
     * @throws Exception
     */
    static public function testImapConnection( Array $account )
    {
        $sync = new Sync;
        $sync->setConfig( self::$config );
        $sync->connect(
            $account[ 'imap_host' ],
            $account[ 'imap_port' ],
            $account[ 'email' ],
            $account[ 'password' ],
            $folder = NULL,
            $setRunning = FALSE );
    }

    /**
     * Reads in a PDOException and checks if it's the SQL server
     * going away. If so, disconnect from the database and attempt
     * to reconnect.
     * @param Container $di Dependency container
     * @param PDOException $e The recently thrown exception
     * @param bool $forwardException On failed re-connect, forward the
     *   termination exception.
     * @throws TerminateException If re-connect fails and the flag
     *   $forwardException is true.
     */
    static public function checkDatabaseException(
        Container $di,
        PDOException $e,
        $forwardException = FALSE )
    {
        $messages = [
            'Lost connection',
            'Error while sending',
            'server has gone away',
            'is dead or not enabled',
            'no connection to the server',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly'
        ];

        if ( ! Fn\contains( $e->getMessage(), $messages ) ) {
            if ( $di[ 'console' ]->daemon ) {
                Message::send(
                    new ErrorMessage(
                        ERR_DATABASE,
                        $e->getMessage(),
                        "This could be a timeout problem, and if so the ".
                        "server is restarting itself."
                    ));
            }
            else {
                $di[ 'log' ]->getLogger()->addNotice( $e->getMessage() );
            }

            throw new TerminateException(
                "System encountered an un-recoverable database error. ".
                "Going to halt now, please see the log file for info." );
            return FALSE;
         }
 
        // This should drop the DB connection
        $di[ 'db' ] = NULL;
 
        try {
            // Create a new database connection. This will throw a
            // TerminateException on failure to connect.
            $di[ 'db' ] = $di[ 'db_factory' ];
            Model::setDb( $di[ 'db' ] );
        }
        catch ( TerminateException $err ) {
            if ( $forwardException ) {
                throw $err;
            }
        }
    }
}