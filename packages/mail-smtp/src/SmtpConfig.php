<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Config\MailConfig;

readonly class SmtpConfig
{
    public function __construct(
        private MailConfig $mailConfig,
    ) {}

    public function host(): string
    {
        return $this->config()['host'] ?? 'localhost';
    }

    public function port(): int
    {
        return $this->config()['port'] ?? 587;
    }

    public function encryption(): ?string
    {
        return $this->config()['encryption'] ?? 'tls';
    }

    public function username(): ?string
    {
        return $this->config()['username'] ?? null;
    }

    public function password(): ?string
    {
        return $this->config()['password'] ?? null;
    }

    public function timeout(): int
    {
        return $this->config()['timeout'] ?? 30;
    }

    public function authMode(): ?string
    {
        return $this->config()['auth_mode'] ?? 'login';
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->mailConfig->driverConfig('smtp');
    }
}
