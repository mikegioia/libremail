<?php

namespace App\Sync\Actions;

use App\Exceptions\NotFound as NotFoundException;
use App\Model\Message as MessageModel;
use App\Model\Task as TaskModel;
use App\Sync\Actions;
use DateTime;
use Exception;
use Laminas\Mail\Exception\InvalidArgumentException;
use Laminas\Mail\Header\ContentType;
use Laminas\Mail\Message as MailMessage;
use Laminas\Mail\Transport\Exception\RuntimeException as TransportRuntimeException;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;
use Pb\Imap\Mailbox;

class Send extends Base
{
    public const TLS = 'tls';
    public const UTF8 = 'UTF-8';
    public const LOCALHOST = 'localhost';
    public const MULTIPART_ALTERNATIVE = 'multipart/alternative';

    /**
     * Tasks are ready if the outbox message is set to be delivered.
     * This function returns true if the `send_after` field is in the
     * past.
     *
     * @return bool
     */
    public function isReady()
    {
        // If the outbox message is deleted, update task reason and get out
        if (1 === (int) $this->outbox->deleted) {
            $this->task->ignore(Actions::IGNORE_OUTBOX_DELETED);

            return false;
        }

        // If the outbox message is sent, update task reason and get out
        if (1 === (int) $this->outbox->sent) {
            $this->task->ignore(Actions::IGNORE_OUTBOX_SENT);

            return false;
        }

        $now = new DateTime;
        $sendAfter = new DateTime($this->outbox->send_after);

        if (! $this->outbox->send_after || $now < $sendAfter) {
            return false;
        }

        return true;
    }

    /**
     * Copies a message to a new folder.
     *
     * @see Base for params
     */
    public function run(Mailbox $mailbox)
    {
        try {
            // Load the sent mail folder, throws NotFoundException
            $sentFolder = $this->folder->getSentByAccount($this->account->id);

            // Create a new temporary message in the sent mail mailbox
            // with purge=1 to ensure it's removed upon re-sync
            $sentMessage = (new MessageModel())->createOrUpdateSent(
                $this->outbox, $sentFolder->id
            );

            // Create the IMAP message and perform the SMTP send
            // If it fails, delete the sent message and update the outbox
            // message to be marked as failed
            $this->sendSmtp();
        } catch (NotFoundException $e) {
            $this->task->log()->addCritical('Sent folder not found!');
            $this->task->fail(Actions::ERR_NO_SQL_SENT);
            $this->outbox->markFailed(Actions::ERR_NO_SQL_SENT);

            return false;
        } catch (TransportRuntimeException $e) {
            $this->task->log()->addError('Message failed transport validation');
            $this->task->log()->addError($e->getMessage());
            $this->task->fail(Actions::ERR_TRANSPORT_FAIL);
            $this->outbox->markFailed(
                trim(Actions::ERR_TRANSPORT_FAIL.'. '.$e->getMessage())
            );

            return false;
        } catch (InvalidArgumentException $e) {
            $this->task->log()->addError('Message failed validation');
            $this->task->log()->addError($e->getMessage());
            $this->task->fail(Actions::ERR_TRANSPORT_FAIL);
            $this->outbox->markFailed(
                trim(Actions::ERR_TRANSPORT_FAIL.'. '.$e->getMessage())
            );

            return false;
        } catch (Exception $e) {
            // UNCOMMENT WHEN TESTING
            // @TODO
            // print_r($e->getMessage());
            // exit('General exception hit!');

            $this->task->log()->addError('Message failed to send');
            $this->task->log()->addError($e->getMessage());
            $this->task->fail(Actions::ERR_TRANSPORT_GENERAL);
            $this->outbox->markFailed(
                trim(Actions::ERR_TRANSPORT_GENERAL.'. '.$e->getMessage())
            );

            return false;
        }

        // Update the status of the outbox message to sent
        $this->outbox->markSent();

        return true;
    }

    public function sendSmtp()
    {
        // Setup SMTP transport using PLAIN authentication
        $transport = (new SmtpTransport())->setOptions(
            new SmtpOptions([
                'name' => gethostname() ?: self::LOCALHOST,
                'host' => $this->account->smtp_host,
                'port' => $this->account->smtp_port,
                'connection_class' => 'plain',
                'connection_config' => [
                    'ssl' => self::TLS,
                    'username' => $this->account->email,
                    'password' => $this->account->password
                ]
            ]));
        // Set the transport to disconnect on destruct
        $transport->setAutoDisconnect(true);

        $message = new MailMessage;

        $message->setEncoding(self::UTF8);
        $message->addFrom($this->account->email, $this->account->name);
        $message->addTo($this->outbox->getAddressList('to'));
        $message->setSubject($this->outbox->subject);

        if ($this->outbox->cc) {
            $message->addCc($this->outbox->getAddressList('cc'));
        }

        if ($this->outbox->bcc) {
            $message->addBcc($this->outbox->getAddressList('bcc'));
        }

        $this->setupMultipartBody($message);

        // Custom headers
        $message->getHeaders()->addHeaderLine('X-Client', 'LibreMail');

        // Away we go!
        $transport->send($message);
    }

    public function getType()
    {
        return TaskModel::TYPE_SEND;
    }

    /**
     * Adds the References and In-Reply-To headers for responding to
     * another message within a thread.
     */
    private function addReplyHeaders(MailMessage $message)
    {
        $referencesHeader = $message->getHeaders()->get('References');
        $inReplyToHeader = $message->getHeaders()->get('In-Reply-To');

        // @todo
    }

    /**
     * Adds an HTML and a text part to the message, updates any
     * of the appropriate message headers.
     */
    private function setupMultipartBody(MailMessage &$message)
    {
        // Text part
        $text = new MimePart($this->outbox->text_plain);
        $text->type = Mime::TYPE_TEXT;
        $text->charset = self::UTF8;
        $text->encoding = Mime::ENCODING_QUOTEDPRINTABLE;

        // HTML part
        $html = new MimePart($this->outbox->text_html);
        $html->type = Mime::TYPE_HTML;
        $html->charset = self::UTF8;
        $html->encoding = Mime::ENCODING_QUOTEDPRINTABLE;

        // Build and set the body parts
        $body = new MimeMessage();
        $body->setParts([$text, $html]);

        // Add this to the message
        $message->setBody($body);

        // Update the content type header accordingly
        $contentTypeHeader = $message->getHeaders()->get('Content-Type');

        if ($contentTypeHeader instanceof ContentType) {
            $contentTypeHeader->setType(self::MULTIPART_ALTERNATIVE);
        }
    }
}
