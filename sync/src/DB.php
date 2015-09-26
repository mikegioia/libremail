<?php

namespace App;

use voku\db\DB as VokuDB;

class DB extends VokuDB
{
    public function select( $table, $where = "1=1", $fields = [] )
    {
        if ( $table === "" ) {
            $this->_displayError( "Invalid table name" );
            return FALSE;
        }

        if ( is_string( $where ) ) {
            $WHERE = $this->escape( $where, FALSE, FALSE );
        }
        elseif ( is_array( $where ) ) {
            $WHERE = $this->_parseArrayPair( $where, "AND" );
        }
        else {
            $WHERE = "";
        }

        $SELECT = ( $fields )
            ? implode( ", ", $fields )
            : "*";
        $FROM = $this->quote_string( $table );
        $sql = "SELECT $SELECT FROM $FROM WHERE ($WHERE);";

        return $this->query( $sql );
    }
}