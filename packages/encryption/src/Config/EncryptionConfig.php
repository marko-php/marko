<?php

declare(strict_types=1);

namespace Marko\Encryption\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class EncryptionConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function key(): string
    {
        return $this->config->getString('encryption.key');
    }

    public function cipher(): string
    {
        return $this->config->getString('encryption.cipher');
    }
}
