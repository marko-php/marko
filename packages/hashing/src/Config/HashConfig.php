<?php

declare(strict_types=1);

namespace Marko\Hashing\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class HashConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function defaultHasher(): string
    {
        return $this->config->getString('hashing.default');
    }

    public function hasHasher(
        string $name,
    ): bool {
        return $this->config->has("hashing.hashers.$name");
    }

    public function getHasherConfig(
        string $name,
    ): array {
        return $this->config->getArray("hashing.hashers.$name");
    }

    public function getBcryptCost(): int
    {
        return $this->config->getInt('hashing.hashers.bcrypt.cost');
    }

    public function getArgon2Memory(): int
    {
        return $this->config->getInt('hashing.hashers.argon2id.memory');
    }

    public function getArgon2Time(): int
    {
        return $this->config->getInt('hashing.hashers.argon2id.time');
    }

    public function getArgon2Threads(): int
    {
        return $this->config->getInt('hashing.hashers.argon2id.threads');
    }
}
