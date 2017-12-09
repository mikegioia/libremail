<?php

namespace App\Inbox;

use App\Inbox;
use League\CLImate\CLImate;
use App\Model\Task as TaskModel;
use App\Model\Message as MessageModel;
use Evenement\EventEmitter as Emitter;

class Rollback
{
    private $cli;
    private $emitter;

    public function __construct( CLImate $cli, Emitter $emitter )
    {
        $this->cli = $cli;
        $this->emitter = $emitter;
    }

    /**
     * Rolls all current logged tasks in the tasks table. Start
     * from the end and revert each one.
     * @throws PDOException
     */
    public function run()
    {
        $count = 0;
        $taskModel = new TaskModel;
        $messageModel = new MessageModel;
        $this->cli->info( "Starting rollback" );
        $tasks = $taskModel->getTasksForRollback();

        if ( ! $tasks ) {
            $this->cli->whisper( "No tasks to roll back" );
            return;
        }

        foreach ( $tasks as $task ) {
            try {
                if ( $this->revertMessage( $task, $messageModel ) ) {
                    $task->revert();
                    $count++;
                }
            }
            catch ( DatabaseUpdateException $e ) {
                throw PDOException( $e );
            }

            $this->emitter->emit( Inbox::EVENT_CHECK_HALT );
        }

        $this->cli->info(
            "Finished rolling back $count task".
            ($count === 1 ? '' : 's') );
    }

    /**
     * Reverts a message to it's previous state.
     * @param TaskModel $task
     * @param MessageModel $message
     */
    private function revertMessage( TaskModel $task, MessageModel $message )
    {
        switch ( $task->type ) {
            case TaskModel::TYPE_READ:
            case TaskModel::TYPE_UNREAD:
                $message->setFlag(
                    $task->message_id,
                    MessageModel::FLAG_SEEN,
                    $task->old_value );
                return TRUE;

            case TaskModel::TYPE_FLAG:
            case TaskModel::TYPE_UNFLAG:
                $message->setFlag(
                    $task->message_id,
                    MessageModel::FLAG_FLAGGED,
                    $task->old_value );
                return TRUE;
        }
    }
}