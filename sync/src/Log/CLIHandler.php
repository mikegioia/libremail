<?php

namespace App\Log;

use Monolog\Logger;
use League\CLImate\CLImate;
use Monolog\Handler\AbstractProcessingHandler;

class CLIHandler extends AbstractProcessingHandler
{
    private $cli;

    public function __construct(CLImate $cli, $level, $bubble = true)
    {
        $this->cli = $cli;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        $this->cli->dim()->inline(
            sprintf(
                '[%s] ',
                $record['datetime']->format(DATE_LOG)
            ));
        $message = $record['level_name'].': '.$record['message'];

        switch ($record['level']) {
            case Logger::DEBUG:
                $this->cli->dim()->inline($message);
                break;
            case Logger::INFO:
                $this->cli->whisper()->inline($message);
                break;
            case Logger::NOTICE:
                $this->cli->lightMagenta()->inline($message);
                break;
            case Logger::WARNING:
                $this->cli->lightYellow()->inline($message);
                break;
            case Logger::ERROR:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                $this->cli->error()->inline($message);
                break;
        }

        $this->cli->br();
    }

    protected function initialize()
    {
        return true;
    }
}
