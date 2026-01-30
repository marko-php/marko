<?php

declare(strict_types=1);

namespace Marko\Mail\Log;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Mail\Address;
use Marko\Mail\Attachment;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

readonly class LogMailer implements MailerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function send(
        Message $message,
    ): bool {
        $context = [
            'from' => $message->from?->email,
            'to' => array_map(fn (Address $address): string => $address->email, $message->to),
            'subject' => $message->subject,
            'has_html' => $message->html !== null,
            'has_text' => $message->text !== null,
            'attachment_count' => count($message->attachments),
        ];

        if ($message->cc !== []) {
            $context['cc'] = array_map(fn (Address $address): string => $address->email, $message->cc);
        }

        if ($message->bcc !== []) {
            $context['bcc'] = array_map(fn (Address $address): string => $address->email, $message->bcc);
        }

        if ($message->attachments !== []) {
            $context['attachments'] = array_map(
                fn (Attachment $attachment): array => [
                    'name' => $attachment->name,
                    'size' => strlen($attachment->content),
                    'mime_type' => $attachment->mimeType,
                ],
                $message->attachments,
            );
        }

        $this->logger->info('Email sent', $context);

        if ($message->text !== null) {
            $this->logger->debug('Email body (text)', ['body' => $message->text]);
        }

        if ($message->html !== null) {
            $this->logger->debug('Email body (html)', ['body' => $message->html]);
        }

        return true;
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        $this->logger->info('Raw email sent', [
            'to' => $to,
            'raw_length' => strlen($raw),
        ]);

        $this->logger->debug('Raw email content', ['content' => $raw]);

        return true;
    }
}
