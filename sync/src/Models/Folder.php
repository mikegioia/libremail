<?php

namespace App\Models;

use Particle\Validator\Validator
  , App\Traits\Model as ModelTrait
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\DatabaseUpdate as DatabaseUpdateException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Folder extends \App\Model
{
    public $id;
    public $name;
    public $account_id;
    public $is_deleted;
    public $created_at;

    use ModelTrait;

    function getData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'account_id' => $this->account_id,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at
        ];
    }

    function getName()
    {
        return $this->name;
    }

    function getAccountId()
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
    function save( $data = [] )
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
        $exists = $this->db()->select(
            'folders', [
                'name' => $this->name,
                'account_id' => $this->account_id
            ])->fetchObject();

        // If it exists, unset is_deleted
        if ( $exists ) {
            $this->is_deleted = 0;
            $this->id = $exists->id;
            unset( $data[ 'id' ] );
            unset( $data[ 'created_at' ] );
            $updated = $this->db()->update(
                'folders', [
                    'is_deleted' => 0
                ], [
                    'name' => $this->name,
                    'account_id' => $this->account_id
                ]);

            if ( ! $updated ) {
                throw new DatabaseUpdateException( FOLDER );
            }

            return;
        }

        $createdAt = new \DateTime;
        unset( $data[ 'id' ] );
        $data[ 'is_deleted' ] = 0;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $newFolderId = $this->db()->insert( 'folders', $data );

        if ( ! $newFolderId ) {
            throw new DatabaseInsertException( FOLDER );
        }

        $this->id = $newFolderId;
    }
}