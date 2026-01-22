<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use DateTimeInterface;

interface CacheItemInterface
{
    /**
     * Get the key for this cache item.
     */
    public function getKey(): string;

    /**
     * Get the value of the cache item.
     */
    public function get(): mixed;

    /**
     * Check if the cache item is a hit (exists and not expired).
     */
    public function isHit(): bool;

    /**
     * Get the expiration time of the cache item.
     */
    public function expiresAt(): ?DateTimeInterface;
}
