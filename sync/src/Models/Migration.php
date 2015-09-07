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
    function run()
    {
        $this->cli()->info( "Running SQL migration scripts" );
    }    
}