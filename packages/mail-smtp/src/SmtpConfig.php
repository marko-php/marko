<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Exception\MailException;

readonly class SmtpConfig
{
    public function __construct(
        private MailConfig $mailConfig,
    ) {}

    /**
     * @throws MailException
     */
    public function host(): string
    {
        $config = $this->config();

        if (!array_key_exists('host', $config)) {
            throw MailException::missingRequiredSmtpKey('host');
        }

        return $config['host'];
    }

    /**
     * @throws MailException
     */
    public function port(): int
    {
        $config = $this->config();

        if (!array_key_exists('port', $config)) {
            throw MailException::missingRequiredSmtpKey('port');
        }

        return $config['port'];
    }

    /**
     * @throws MailException
     */
    public function encryption(): string
    {
        $config = $this->config();

        if (!array_key_exists('encryption', $config)) {
            throw MailException::missingRequiredSmtpKey('encryption');
        }

        return $config['encryption'];
    }

    /**
     * username and password are intentionally nullable — null means no-auth SMTP.
     * They MUST NOT throw when absent; a missing key is treated as null here.
     */
    public function username(): ?string
    {
        return $this->config()['username'] ?? null;
    }

    /**
     * username and password are intentionally nullable — null means no-auth SMTP.
     * They MUST NOT throw when absent; a missing key is treated as null here.
     */
    public function password(): ?string
    {
        return $this->config()['password'] ?? null;
    }

    /**
     * @throws MailException
     */
    public function timeout(): int
    {
        $config = $this->config();

        if (!array_key_exists('timeout', $config)) {
            throw MailException::missingRequiredSmtpKey('timeout');
        }

        return $config['timeout'];
    }

    /**
     * @throws MailException
     */
    public function authMode(): string
    {
        $config = $this->config();

        if (!array_key_exists('auth_mode', $config)) {
            throw MailException::missingRequiredSmtpKey('auth_mode');
        }

        return $config['auth_mode'];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->mailConfig->driverConfig('smtp');
    }
}
