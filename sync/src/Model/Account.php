<?php

namespace App\Model;

use PDO
  , DateTime
  , App\Model
  , Particle\Validator\Validator
  , App\Traits\Model as ModelTrait
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\AccountExists as AccountExistsException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException
  , App\Exceptions\DatabaseUpdate as DatabaseUpdateException;

class Account extends Model
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
     * @throws AccountExistsException
     * @throws DatabaseInsertException
     * @throws DatabaseUpdateException
     */
    public function save( $data = [], $updateIfExists = FALSE )
    {
        $this->setData( $data );
        $this->validate();
        $data = $this->getData();

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

    /**
     * Validate the account data.
     * @throws ValidationException
     */
    public function validate()
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

        if ( ! $val->validate( $this->getData() ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "There was a problem creating this account."
                ));
        }
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

    /**
     * Uses the email address to try and infer the IMAP service.
     */
    public function loadServiceFromEmail()
    {
        // Build the array of services
        $config = [];
        $services = $this->config( 'email.services' );

        foreach ( $services as $serviceName ) {
            $key = strtolower( $serviceName );
            $service = $this->config( "email.$key" );

            if ( isset( $service[ 'domain' ] ) ) {
                $config[ $service[ 'domain' ] ] = $service;
                $config[ $service[ 'domain' ] ][ 'key' ] = $key;
            }
        }

        // Get the domain from the email
        $emailParts = explode( '@', $this->email );

        if ( count( $emailParts ) !== 2 ) {
            return;
        }

        if ( ! isset( $config[ $emailParts[ 1 ] ] ) ) {
            $this->service = DEFAULT_SERVICE;

            if ( ! $this->imap_port ) {
                $this->imap_port = $config[ DEFAULT_SERVICE ][ 'port' ];
            }

            return;
        }

        $this->service = $config[ $emailParts[ 1 ] ][ 'key' ];
        $this->imap_host = $config[ $emailParts[ 1 ] ][ 'host' ];
        $this->imap_port = $config[ $emailParts[ 1 ] ][ 'port' ];
    }
}