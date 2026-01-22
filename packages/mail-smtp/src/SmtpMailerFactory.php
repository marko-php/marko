<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Contracts\MailerInterface;

class SmtpMailerFactory
{
    public function __construct(
        private SmtpConfig $config,
        private SocketInterface $socket,
    ) {}

    public function create(): MailerInterface
    {
        return new SmtpMailer(
            transport: new SmtpTransport($this->socket),
            config: $this->config,
        );
    }
}
