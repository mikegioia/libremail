<?php

/**
 * Handles all SQL migration scripts. This file is designed
 * to read in the SQL scripts from the /db directory and run
 * each sequentially. If the filename exists in our migrations
 * table (unless it's script 0 to create this table) then skip
 * that script.
 */

namespace App\Models;

class Migration extends \App\Model
{
    /**
     * Read each file in the script folder and check to see
     * if it's been run before. If so, skip it. If not, run
     * the script and log it in the migrations table.
     * @param string $scriptDir Path to script files
     */
    function run()
    {
        $this->cli()->info( "Running SQL migration scripts" );

        foreach ( glob( DBSCRIPTS ) as $filename ) {
            $query = file_get_contents( $filename );
            $script = basename( $filename );

            if ( $this->isRunAlready( $script ) ) {
                $this->cli()->dim( "[skip] $script" );
                continue;
            }

            $this->cli()->inline( "[....] Running $script" );
            $this->db()->query( $query );
            $this->markRun( $script );
            $this->cli()
                ->inline( "\r[" )
                ->greenInline( " ok " )
                ->inline( "] Running $script" )
                ->br();
        }
    }

    /**
     * Checks if the script was already run.
     * @param string $script Filename for the script
     */
    private function isRunAlready( $script )
    {
        return $this->db()->select(
            'migrations', [
                'name' => $script
            ])->fetchObject();
    }

    /**
     * Marks a script as 'run'.
     * @param string $script Filename for the script
     */
    private function markRun( $script )
    {
        return $this->db()->insert(
            'migrations', [
                'name' => $script,
                'created_at' => time()
            ]);
    }
}