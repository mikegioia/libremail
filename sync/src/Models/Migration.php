<?php

/**
 * Handles all SQL migration scripts. This file is designed
 * to read in the SQL scripts from the /db directory and run
 * each sequentially. If the filename exists in our migrations
 * table (unless it's script 0 to create this table) then skip
 * that script.
 */

namespace App\Models;

use Fn
  , DateTime
  , App\Traits\Model as ModelTrait;

class Migration extends \App\Model
{
    public $name;
    public $created_at;

    use ModelTrait;

    /**
     * Read each file in the script folder and check to see
     * if it's been run before. If so, skip it. If not, run
     * the script and log it in the migrations table.
     * @param string $scriptDir Path to script files
     */
    public function run()
    {
        $this->cli()->info( "Running SQL migration scripts" );

        foreach ( glob( DBSCRIPTS ) as $filename ) {
            $script = basename( $filename, ".sql" );
            $queries = explode( "\n\n", file_get_contents( $filename ) );

            if ( $this->isRunAlready( $script ) ) {
                $this->cli()->dim( "[skip] {$script}.sql" );
                continue;
            }

            $this->cli()->inline( "[....] Running {$script}.sql" );

            foreach ( $queries as $query ) {
                if ( ! $this->db()->query( $query ) ) {
                    $this->cli()
                        ->inline( "\r[" )
                        ->redInline( "fail" )
                        ->inline( "] Running {$script}.sql" )
                        ->br()
                        ->br()
                        ->error( $this->getError() );
                    return;
                }
            }

            $this->markRun( $script );
            $this->cli()
                ->inline( "\r[" )
                ->greenInline( " ok " )
                ->inline( "] Running {$script}.sql" )
                ->br();
        }
    }

    public function setMaxAllowedPacket( $mb = 16 )
    {
        $size = $this->db()
            ->query( "SHOW VARIABLES LIKE 'max_allowed_packet';" )
            ->fetch();
        $value = Fn\get( $size, 'Value' );
        $newSize = (int) ( $mb * 1024 * 1024 );

        if ( ! $value || $value < $newSize ) {
            $stmt = $this->db()
                ->query( "SET GLOBAL max_allowed_packet = $newSize;" );
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Checks if the script was already run.
     * @param string $script Filename for the script
     */
    private function isRunAlready( $script )
    {
        $migrationsExists = $this->db()
            ->query( "show tables like 'migrations';" )
            ->fetch();

        if ( ! $migrationsExists ) {
            return FALSE;
        }

        return $this->db()
            ->select()
            ->from( 'migrations' )
            ->where( 'name', '=', $script )
            ->execute()
            ->fetchObject( $this->getClass() );
    }

    /**
     * Marks a script as 'run'.
     * @param string $script Filename for the script
     */
    private function markRun( $script )
    {
        $createdAt = new DateTime;

        return $this->db()
            ->insert([ 'name', 'created_at' ])
            ->into( 'migrations' )
            ->values([
                $script,
                $createdAt->format( DATE_DATABASE )
            ])->execute();
    }
}