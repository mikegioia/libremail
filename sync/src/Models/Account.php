<?php

namespace App\Models;

use Particle\Validator\Validator as Validator;

class Account extends \App\Model
{
    public $id;
    public $email;
    public $service;
    public $password;
    public $imap_host;
    public $imap_port;
    public $imap_flags;
    public $created_at;

    /**
     * Create a new account record.
     * @param array $data
     */
    static function create( $data )
    {
        $val = new Validator();
        $val->required( 'email', 'Email' )->lengthBetween( 0, 100 );
        $val->required( 'service', 'Service type' )
            ->inArray( self::config( 'email.services' ) );
        $val->required( 'password', 'Password' )->lengthBetween( 0, 100 );
        $val->optional( 'imap_host', 'IMAP host' )->lengthBetween( 0, 50 );
        $val->optional( 'imap_port', 'IMAP port' )->lengthBetween( 0, 5 );
        $val->optional( 'imap_flags', 'IMAP flags' )->lengthBetween( 0, 50 );

        if ( ! $val->validate( $data ) ) {
            throw new \Exception(
                self::getErrorString(
                    $val,
                    "There was a problem creating this account."
                ));
        }

        // Check if this email exists
        $exists = self::db()->select(
            'accounts', [
                'email' => $data[ 'email' ]
            ])->fetchObject();

        if ( $exists ) {
            throw new \Exception(
                "The account for '{$data['email']}' already exists." );
        }

        $createdAt = new \DateTime();
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $data[ 'service' ] = strtolower( $data[ 'service' ] );

        if ( ! self::db()->insert( 'accounts', $data ) ) {
            throw new \Exception( "There was a problem saving this account." );
        }
    }
}