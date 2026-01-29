<?php

declare(strict_types=1);

namespace Marko\Mail\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Mail\Exception\MailException;

readonly class MailConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Ensures the mail configuration exists.
     *
     * @throws MailException if mail configuration is not found
     */
    public function ensureConfigExists(): void
    {
        if (!$this->config->has('mail')) {
            throw MailException::configFileNotFound('config/mail.php');
        }
    }

    public function driver(): string
    {
        return $this->config->getString('mail.driver');
    }

    public function fromAddress(): string
    {
        return $this->config->getString('mail.from.address');
    }

    public function fromName(): string
    {
        return $this->config->getString('mail.from.name');
    }

    /**
     * @return array<string, mixed>
     */
    public function driverConfig(
        string $driver,
    ): array {
        return $this->config->getArray("mail.$driver");
    }
}
