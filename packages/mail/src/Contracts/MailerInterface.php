<?php

declare(strict_types=1);

namespace Marko\Mail\Contracts;

use Marko\Mail\Exception\TransportException;
use Marko\Mail\Message;

interface MailerInterface
{
    /**
     * Send an email message.
     *
     * @throws TransportException On delivery failure
     */
    public function send(
        Message $message,
    ): bool;

    /**
     * Send a raw email (pre-formatted string).
     *
     * @throws TransportException On delivery failure
     */
    public function sendRaw(
        string $to,
        string $raw,
    ): bool;
}
