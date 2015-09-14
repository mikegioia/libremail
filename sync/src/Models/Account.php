<?php

namespace App\Models;

use Particle\Validator\Validator
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\AccountExists as AccountExistsException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Account extends \App\Model
{
    public $id;
    public $email;
    public $service;
    public $password;
    public $is_active;
    public $imap_host;
    public $imap_port;
    public $imap_flags;
    public $created_at;

    /**
     * Create a new account record.
     * @param array $data
     * @throws ValidationException
     * @throws AccountExistsException
     * @throws DatabaseInsertException
     */
    static function create( $data )
    {
        $val = new Validator;
        $val->required( 'email', 'Email' )->lengthBetween( 0, 100 );
        $val->required( 'service', 'Service type' )
            ->inArray( self::config( 'email.services' ) );
        $val->required( 'password', 'Password' )->lengthBetween( 0, 100 );
        $val->optional( 'imap_host', 'IMAP host' )->lengthBetween( 0, 50 );
        $val->optional( 'imap_port', 'IMAP port' )->lengthBetween( 0, 5 );
        $val->optional( 'imap_flags', 'IMAP flags' )->lengthBetween( 0, 50 );

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
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
            throw new AccountExistsException( $data[ 'email' ] );
        }

        $createdAt = new \DateTime;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $data[ 'service' ] = strtolower( $data[ 'service' ] );
        $data[ 'is_active' ] = 1;

        if ( ! self::db()->insert( 'accounts', $data ) ) {
            throw new DatabaseInsertException( ACCOUNT );
        }
    }

    function getActive()
    {
        $accounts = $this->db()->select(
            'accounts', [
                'is_active =' => 1
            ])->fetchAllObject();

        return $this->populate( $accounts, "\App\Models\Account" );
    }
}