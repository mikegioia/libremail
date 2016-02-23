<?php

namespace App\Models;

use PDO
  , DateTime
  , Particle\Validator\Validator
  , App\Traits\Model as ModelTrait
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

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'service' => $this->service,
            'password' => $this->password,
            'is_active' => $this->is_active,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_flags' => $this->imap_flags,
            'created_at' => $this->created_at
        ];
    }

    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Create a new account record.
     * @param array $data
     * @throws ValidationException
     * @throws AccountExistsException
     * @throws DatabaseInsertException
     */
    public function save( $data = [], $updateIfExists = FALSE )
    {
        $val = new Validator;
        $val->required( 'email', 'Email' )->lengthBetween( 0, 100 );
        $val->required( 'service', 'Service type' )
            ->inArray(
                array_map(
                    'strtolower',
                    $this->config( 'email.services' )
                ));
        $val->required( 'password', 'Password' )->lengthBetween( 0, 100 );
        $val->optional( 'imap_host', 'IMAP host' )->lengthBetween( 0, 50 );
        $val->optional( 'imap_port', 'IMAP port' )->lengthBetween( 0, 5 );
        $val->optional( 'imap_flags', 'IMAP flags' )->lengthBetween( 0, 50 );
        $this->setData( $data );
        $data = $this->getData();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "There was a problem creating this account."
                ));
        }

        // Check if this email exists
        $exists = $this->db()
            ->select()
            ->from( 'accounts' )
            ->where( 'email', '=', $data[ 'email' ] )
            ->execute()
            ->fetchObject();

        if ( $exists ) {
            if ( ! $updateIfExists ) {
                throw new AccountExistsException( $data[ 'email' ] );
            }

            $this->id = $exists->id;
            unset( $data[ 'id' ] );
            unset( $data[ 'created_at' ] );
            $updated = $this->db()
                ->update( $data )
                ->table( 'accounts' )
                ->where( 'id', '=', $exists->id )
                ->execute();

            if ( $updated === FALSE ) {
                throw new DatabaseUpdateException( FOLDER );
            }

            return;
        }

        $createdAt = new DateTime;
        unset( $data[ 'id' ] );
        $data[ 'is_active' ] = 1;
        $data[ 'service' ] = strtolower( $data[ 'service' ] );
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );

        $newAccountId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'accounts' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newAccountId ) {
            throw new DatabaseInsertException(
                ACCOUNT,
                $this->getError() );
        }

        $this->id = $newAccountId;
    }

    public function getActive()
    {
        return $this->db()
            ->select()
            ->from( 'accounts' )
            ->where( 'is_active', '=', 1 )
            ->execute()
            ->fetchAll( PDO::FETCH_CLASS, $this->getClass() );
    }
}