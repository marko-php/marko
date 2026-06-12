<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\MailException;
use Marko\Mail\Exception\TransportException;

readonly class SmtpMailerFactory
{
    public function __construct(
        private SmtpConfig $smtpConfig,
        private SocketInterface $socket,
    ) {}

    /**
     * @throws MailException|TransportException
     */
    public function create(): MailerInterface
    {
        $transport = new SmtpTransport($this->socket);

        $host = $this->smtpConfig->host();
        $port = $this->smtpConfig->port();
        $encryption = $this->smtpConfig->encryption();
        $hostname = gethostname();

        $transport->connect($host, $port, $encryption);
        $transport->ehlo($hostname);

        if ($encryption === 'tls') {
            $transport->startTls();
            $transport->ehlo($hostname);
        }

        $username = $this->smtpConfig->username();
        $password = $this->smtpConfig->password();

        if ($username !== null && $password !== null) {
            $transport->authenticate($username, $password, $this->smtpConfig->authMode());
        }

        return new SmtpMailer(transport: $transport);
    }
}
