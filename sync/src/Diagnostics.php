<?php

namespace App;

use Fn
  , App\Log
  , Exception
  , PDOException
  , Pimple\Container
  , App\Models\Migration as MigrationModel
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
    private $config;
    private $console;

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
        $this->config = $di[ 'config' ];
        $this->console = $di[ 'console' ];
        $this->log = $di[ 'log' ]->getLogger();
        $this->tests = [
            self::TEST_LOG_PATH => [
                'code' => 1,
                'status' => NULL,
                'message' => NULL,
                'name' => 'log path is writable'
            ],
            self::TEST_DB_CONN => [
                'code' => 2,
                'status' => NULL,
                'message' => NULL,
                'name' => 'database connection is alive'
            ],
            self::TEST_DB_EXISTS => [
                'code' => 3,
                'status' => NULL,
                'message' => NULL,
                'name' => 'database was created'
            ],
            self::TEST_MAX_PACKET => [
                'code' => 4,
                'status' => NULL,
                'message' => NULL,
                'name' => 'max_allowed_packet is safe'
            ],
            self::TEST_ATTACH_PATH => [
                'code' => 5,
                'status' => NULL,
                'message' => NULL,
                'name' => 'attachment path is writable'
            ]
        ];
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
     * Check if the log path is writable.
     */
    public function testLogPathWritable()
    {
        $this->startTest( self::TEST_LOG_PATH );

        try {
            $path = Log::preparePath( $this->config[ 'log' ][ 'path' ] );
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
            $dbConfig = $this->config[ 'sql' ];
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

        // If we're not in diagnostic mode, then use exceptions
        if ( ! $this->console->diagnostics ) {
            if ( $status === STATUS_ERROR ) {
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

        $this->tests[ $test ][ 'status' ] = $status;
        $this->tests[ $test ][ 'message' ] = $message;
    }

    /**
     * Handle any output after the tests have finished.
     */
    private function finish()
    {
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
        if ( strpos( $e->getMessage(), "server has gone away" ) === FALSE ) {
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