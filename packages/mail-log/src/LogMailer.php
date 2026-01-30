<?php

declare(strict_types=1);

namespace Marko\Mail\Log;

use Marko\Log\Contracts\LoggerInterface;
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
        return true;
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        return true;
    }
}
