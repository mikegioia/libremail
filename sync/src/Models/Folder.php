<?php

namespace App\Models;

use DateTime
  , Particle\Validator\Validator
  , App\Traits\Model as ModelTrait
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\DatabaseUpdate as DatabaseUpdateException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Folder extends \App\Model
{
    public $id;
    public $name;
    public $deleted;
    public $ignored;
    public $account_id;
    public $created_at;

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deleted' => $this->deleted,
            'ignored' => $this->ignored,
            'account_id' => $this->account_id,
            'created_at' => $this->created_at
        ];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAccountId()
    {
        return (int) $this->account_id;
    }

    /**
     * Create a new folder record. Updates a folder to be active
     * if it exists in the system already.
     * @param array $data
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    public function save( $data = [] )
    {
        $val = new Validator;
        $val->required( 'name', 'Name' )->lengthBetween( 0, 255 );
        $val->required( 'account_id', 'Account ID' )->integer();
        $this->setData( $data );
        $data = $this->getData();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                $this->getErrorString(
                    $val,
                    "This folder is missing required data."
                ));
        }

        // Check if this folder exists
        $exists = $this->db()
            ->select()
            ->from( 'folders' )
            ->where( 'name', '=', $this->name )
            ->where( 'account_id', '=', $this->account_id )
            ->execute()
            ->fetchObject();

        // If it exists, unset deleted
        if ( $exists ) {
            $this->deleted = 0;
            $this->id = $exists->id;
            $this->ignored = $exists->ignored;
            $updated = $this->db()
                ->update([
                    'deleted' => 0
                ])
                ->table( 'folders' )
                ->where( 'id', '=', $this->id )
                ->execute();

            if ( $updated === FALSE ) {
                throw new DatabaseUpdateException( FOLDER );
            }

            return;
        }

        $createdAt = new DateTime;
        unset( $data[ 'id' ] );
        $data[ 'deleted' ] = 0;
        $data[ 'ignored' ] = 0;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $newFolderId = $this->db()
            ->insert( array_keys( $data ) )
            ->into( 'folders' )
            ->values( array_values( $data ) )
            ->execute();

        if ( ! $newFolderId ) {
            throw new DatabaseInsertException( FOLDER );
        }

        $this->id = $newFolderId;
    }

    /**
     * Finds a folder by account and name.
     * @param int $accountId
     * @param string $name
     * @param bool $failOnNotFound If set, throw an Exception when
     *   the folder isn't found
     * @return bool | FolderModel
     */
    public function getByName( $accountId, $name, $failOnNotFound = FALSE )
    {
        $this->requireInt( $accountId, "Account ID" );
        $this->requireString( $name, "Folder name" );

        $folder = $this->db()
            ->select()
            ->from( 'folders' )
            ->where( 'name', '=', $name )
            ->where( 'account_id', '=', $accountId )
            ->execute()
            ->fetchObject( $this->getClass() );

        $this->handleNotFound( $folder, FOLDER, $failOnNotFound );

        return $folder;
    }
}