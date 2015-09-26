<?php

namespace App\Models;

use Particle\Validator\Validator
  , App\Exceptions\Validation as ValidationException
  , App\Exceptions\DatabaseInsert as DatabaseInsertException;

class Folder extends \App\Model
{
    public $id;
    public $name;
    public $account_id;
    public $is_deleted;
    public $created_at;

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
            $this->id = $exists->id;
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
        $data[ 'is_deleted' ] = 0;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );
        $newFolder = $this->db()->insert( 'folders', $data );

        if ( ! $newFolder ) {
            throw new DatabaseInsertException( FOLDER );
        }

        $this->id = $newFolder->id;
    }
}