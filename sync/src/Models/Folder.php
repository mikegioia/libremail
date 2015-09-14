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

    /**
     * Create a new folder record. Updates a folder to be active
     * if it exists in the system already.
     * @param array $data
     * @throws ValidationException
     * @throws DatabaseUpdateException
     * @throws DatabaseInsertException
     */
    static function create( $data )
    {
        $val = new Validator;
        $val->required( 'name', 'Name' )->lengthBetween( 0, 255 );
        $val->required( 'account_id', 'Account ID' )->integer();

        if ( ! $val->validate( $data ) ) {
            throw new ValidationException(
                self::getErrorString(
                    $val,
                    "This folder is missing required data."
                ));
        }

        // Check if this folder exists
        $exists = self::db()->select(
            'folders', [
                'name' => $data[ 'name' ],
                'account_id' => $data[ 'account_id' ]
            ])->fetchObject();

        // If it exists, unset is_deleted
        if ( $exists ) {
            $updated = self::db()->update(
                'folders', [
                    'is_deleted' => 0
                ], [
                    'name' => $data[ 'name' ],
                    'account_id' => $data[ 'account_id' ]
                ]);

            if ( ! $updated ) {
                throw new DatabaseUpdateException( FOLDER );
            }

            return;
        }

        $createdAt = new \DateTime;
        $data[ 'is_deleted' ] = 0;
        $data[ 'created_at' ] = $createdAt->format( DATE_DATABASE );

        if ( ! self::db()->insert( 'folders', $data ) ) {
            print_r($data);exit();
            throw new DatabaseInsertException( FOLDER );
        }
    }
}