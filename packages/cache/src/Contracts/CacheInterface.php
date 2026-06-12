<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use Marko\Cache\Exceptions\InvalidKeyException;

interface CacheInterface
{
    /**
     * Get a value from the cache.
     *
     * @throws InvalidKeyException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed;

    /**
     * Store a value in the cache.
     *
     * @param int|null $ttl Time to live in seconds, null for default TTL
     *
     * @throws InvalidKeyException
     */
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool;

    /**
     * Check if a key exists in the cache.
     *
     * @throws InvalidKeyException
     */
    public function has(
        string $key,
    ): bool;

    /**
     * Delete a value from the cache.
     *
     * @throws InvalidKeyException
     */
    public function delete(
        string $key,
    ): bool;

    /**
     * Clear all values from the cache.
     */
    public function clear(): bool;

    /**
     * Get a cache item (includes metadata).
     *
     * @throws InvalidKeyException
     */
    public function getItem(
        string $key,
    ): CacheItemInterface;

    /**
     * Get multiple values from the cache.
     *
     * @param array<string> $keys
     *
     * @throws InvalidKeyException
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable;

    /**
     * Store multiple values in the cache.
     *
     * @param array<string, mixed> $values
     * @param int|null             $ttl    Time to live in seconds
     *
     * @throws InvalidKeyException
     */
    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool;

    /**
     * Delete multiple values from the cache.
     *
     * @param array<string> $keys
     *
     * @throws InvalidKeyException
     */
    public function deleteMultiple(
        array $keys,
    ): bool;

    /**
     * Atomically increment the integer stored at $key and return the new value.
     *
     * If the key does not exist, it is created with a value of 1 and the TTL is
     * applied at that moment. On subsequent increments the TTL is NOT reset —
     * resetting on every call would turn a fixed rate-limit window into a
     * never-closing window.
     *
     * @throws InvalidKeyException
     */
    public function increment(
        string $key,
        int $ttl,
    ): int;
}
