<?php

declare(strict_types=1);

namespace Marko\Hashing\Factory;

use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Hash\Argon2Hasher;
use Marko\Hashing\Hash\BcryptHasher;

readonly class HasherFactory
{
    public function __construct(
        private HashConfig $config,
    ) {}

    /**
     * @throws HasherNotFoundException
     */
    public function make(
        string $name,
    ): HasherInterface {
        return match ($name) {
            'bcrypt' => new BcryptHasher(
                cost: $this->config->getBcryptCost(),
            ),
            'argon2id' => new Argon2Hasher(
                memory: $this->config->getArgon2Memory(),
                time: $this->config->getArgon2Time(),
                threads: $this->config->getArgon2Threads(),
            ),
            default => throw HasherNotFoundException::forName($name),
        };
    }
}
