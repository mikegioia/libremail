<?php

namespace App\Server;

use App\Command;
use App\Exceptions\Terminate as TerminateException;
use App\Log;
use App\Message;
use App\Message\TaskMessage;
use App\Task;
use App\Traits\JsonMessage as JsonMessageTrait;
use App\Util;
use Exception;
use PDOException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use SplObjectStorage;

class StatsServer implements MessageComponentInterface
{
    // For JSON message handling
    use JsonMessageTrait;

    private $log;
    private $loop;
    private $clients;
    private $lastMessage;

    // Streams
    private $read;
    private $write;

    public function __construct(Log $log, LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->log = $log->getLogger();
        $this->clients = new SplObjectStorage();

        // Set up the STDIN and STDOUT streams
        $this->setupInputStreams();
    }

    /**
     * Send a message to all connected clients.
     *
     * @param string $message JSON encoded message
     */
    public function broadcast(string $message)
    {
        $obj = @json_decode($message);

        if (! $this->lastMessage
            || (isset($obj->type) && Message::STATS === $obj->type)
        ) {
            $this->lastMessage = $message;
        }

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->log->debug(
            'New socket connection opened from #'.Util::get($conn, 'resourceId', '?')
        );
        $this->clients->attach($conn);

        if ($this->lastMessage) {
            $conn->send($this->lastMessage);
        }

        // Send a command to return the status of the sync script.
        // If the daemon gets this and the sync script isn't running,
        // then it'll trigger an event to broadcast an offline message.
        $this->write->write(Command::getMessage(Command::HEALTH));
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->log->debug(
            'Closing socket connection to #'.Util::get($conn, 'resourceId', '?')
        );
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $this->log->notice(
            'Error encountered from socket connection: '.$e->getMessage()
        );
        $this->clients->detach($conn);
        $conn->close();

        // Forward the error
        throw $e;
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        $logMessage = sprintf('New socket message from #%s: %s',
            Util::get($from, 'resourceId', '?'),
            $message
        );

        $this->log->debug($logMessage);
        $this->processMessage($message, null);
    }

    private function setupInputStreams()
    {
        $this->read = new ReadableResourceStream(STDIN, $this->loop);

        // The data can come in JSON encoded. If so, we want to
        // detect this format and keep reading until we reach the
        // end of the JSON stream.
        $this->read->on('data', function ($data) {
            $this->processMessage($data, null);
        });

        $this->write = new WritableResourceStream(STDOUT, $this->loop);
    }

    private function processMessage(string $message, string $key = null)
    {
        if (! $this->write
            || ! ($parsed = $this->parseMessage($message))
        ) {
            return;
        }

        // A text command came in. This could be something like
        // restart the sync (wake up), force-fetch the stats,
        // shutdown the sync, etc. Send this to the daemon. If it's
        // a valid command, then send it to STDOUT.
        if ((new Command)->isValid($parsed)) {
            $this->write->write($parsed);
        }

        // If it's a message of type 'task', execute that task
        if (Message::isValid($parsed)) {
            try {
                $message = Message::make($parsed);

                if ($message instanceof TaskMessage) {
                    $task = Task::make($message->task, (array) $message->data);
                    $response = $task->run($this);

                    // The response itself can be a command. If it
                    // is, send it to the Daemon.
                    if ((new Command())->isValid($response)) {
                        $this->write->write($response);
                    }
                }
            } catch (PDOException $e) {
                // Keep throwing these
                throw $e;
            } catch (TerminateException $e) {
                // And these
                throw $e;
            } catch (Exception $e) {
                // Otherwise just log and move on
                $this->log->notice($e->getMessage());
            }
        }
    }

    private function handleMessage(string $json, string $key)
    {
        $this->broadcast($json);
    }
}
