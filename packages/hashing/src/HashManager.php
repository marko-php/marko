<?php

declare(strict_types=1);

namespace Marko\Hashing;

use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Factory\HasherFactory;

class HashManager
{
    /** @var array<string, HasherInterface> */
    private array $hashers = [];

    public function __construct(
        private readonly HashConfig $config,
        private readonly HasherFactory $factory,
    ) {}

    /**
     * @throws HasherNotFoundException
     */
    public function hash(
        string $value,
    ): string {
        return $this->hasher()->hash($value);
    }

    /**
     * @throws HasherNotFoundException
     */
    public function verify(
        string $value,
        string $hash,
    ): bool {
        return $this->hasher()->verify($value, $hash);
    }

    /**
     * @throws HasherNotFoundException
     */
    public function needsRehash(
        string $hash,
    ): bool {
        return $this->hasher()->needsRehash($hash);
    }

    /**
     * @throws HasherNotFoundException
     */
    public function hasher(
        ?string $name = null,
    ): HasherInterface {
        $name ??= $this->config->defaultHasher();

        if (!isset($this->hashers[$name])) {
            $this->hashers[$name] = $this->factory->make($name);
        }

        return $this->hashers[$name];
    }

    public function has(
        string $name,
    ): bool {
        try {
            $this->hasher($name);

            return true;
        } catch (HasherNotFoundException) {
            return false;
        }
    }
}
