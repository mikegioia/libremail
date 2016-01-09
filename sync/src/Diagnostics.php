<?php

namespace App;

use PDOException
  , Pimple\Container
  , App\Exceptions\Terminate as TerminateException;

class Diagnostics
{
    /**
     * Runs a series of diagnostic checks like writing to the
     * log file, connecting to the database, checking the path
     * to save attachments is writeable, and others.
     */
    public function run()
    {
        // @TODO
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