<?php

namespace App;

class Url
{
    static private $base;

    static public function setBase( $base )
    {
        self::$base = $base;
    }

    static public function get( $path, $params = [] )
    {
        return self::$base . $path
            . ( $params ? '?'. http_build_query( $params ) : '' );
    }

    static public function make( $path, ...$parts )
    {
        return self::$base . vsprintf( $path, $parts );
    }

    static public function starred( $page )
    {
        return self::make( '/starred/%s', $page );
    }

    static public function folder( $folderId, $page = NULL )
    {
        return ( $page )
            ? self::make( '/folder/%s/%s', $folderId, $page )
            : self::make( '/folder/%s', $folderId );
    }

    static public function redirect( $path, $params = [], $code = 303 )
    {
        header( 'Location: '. self::get( $path, $params ), $code );
        die();
    }

    static public function redirectRaw( $url, $code = 303 )
    {
        header( 'Location: '. $url, $code );
        die();
    }

    static public function postParam( $key, $default = NULL )
    {
        return ( isset( $_POST[ $key ] ) )
            ? $_POST[ $key ]
            : $default;
    }

    static public function getParam( $key, $default = NULL )
    {
        return ( isset( $_GET[ $key ] ) )
            ? $_GET[ $key ]
            : $default;
    }

    static public function actionRedirect( $urlId, $folderId, $page )
    {
        if ( $urlId === INBOX ) {
            self::redirect( '/' );
        }

        if ( $urlId === STARRED ) {
            self::redirectRaw( self::starred( $page ?: 1 ) );
        }

        self::redirectRaw( self::folder( $folderId, $page ) );
    }

    static public function getRefUrl( $default = '/' )
    {
        $ref = ( isset( $_SERVER[ 'HTTP_REFERER' ] ) )
            ? $_SERVER[ 'HTTP_REFERER' ]
            : '';

        // Only use this if we're on the same domain
        $len = strlen( self::$base );

        if ( strncmp( $ref, self::$base, $len ) !== 0 ) {
            return self::get( $default );
        }

        $path = htmlspecialchars( substr( $ref, $len ) );

        return self::get( $path );
    }
}