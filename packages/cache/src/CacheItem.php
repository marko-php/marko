<?php

declare(strict_types=1);

namespace Marko\Cache;

use DateTimeImmutable;
use DateTimeInterface;
use Marko\Cache\Contracts\CacheItemInterface;

readonly class CacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private mixed $value,
        private bool $isHit,
        private ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function expiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * Create a cache miss item.
     */
    public static function miss(
        string $key,
    ): self {
        return new self(
            key: $key,
            value: null,
            isHit: false,
        );
    }

    /**
     * Create a cache hit item.
     */
    public static function hit(
        string $key,
        mixed $value,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        return new self(
            key: $key,
            value: $value,
            isHit: true,
            expiresAt: $expiresAt,
        );
    }
}
