<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use Marko\Cache\Exceptions\CacheException;
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
     * @param int|null $ttl Time to live in seconds, null for the default TTL, 0 for no expiry
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
     * @param int|null             $ttl    Time to live in seconds, null for the default TTL, 0 for no expiry
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
     * The counter is a plain integer: for every driver, get(), getItem() and
     * getMultiple() on an incremented key return an `int`.
     *
     * A driver that cannot read or persist the counter throws a CacheException;
     * it never fails open by returning 1, which would let a rate limiter allow
     * every request.
     *
     * @throws InvalidKeyException|CacheException
     */
    public function increment(
        string $key,
        int $ttl,
    ): int;

    /**
     * Store a value only if the key holds no unexpired value, atomically.
     *
     * The check and the write are a single atomic step in every driver, so of
     * any number of concurrent callers exactly one gets `true`. Use it to make
     * an action run once, instead of has() followed by set(), which races.
     *
     * @param int|null $ttl Time to live in seconds, null for the default TTL, 0 for no expiry
     *
     * @throws InvalidKeyException|CacheException
     *
     * @return bool true when the value was stored, false when an unexpired value already exists
     */
    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool;

    /**
     * Return the cached value, or compute it with $callback on a miss and store it.
     *
     * A hit is decided by getItem()->isHit(), so a cached `null` is returned
     * without calling $callback, and a `null` returned by $callback is cached.
     *
     * This is not a lock: two callers that miss at the same moment both run
     * $callback, and the last write wins.
     *
     * @param int|null $ttl Time to live in seconds, null for the default TTL, 0 for no expiry
     *
     * @throws InvalidKeyException
     */
    public function remember(
        string $key,
        ?int $ttl,
        callable $callback,
    ): mixed;

    /**
     * Get a value from the cache and delete it.
     *
     * Not atomic: two concurrent pull() calls can both read the value. Use
     * add() or increment() when exactly one caller must win.
     *
     * @throws InvalidKeyException
     */
    public function pull(
        string $key,
        mixed $default = null,
    ): mixed;

    /**
     * Store a value with no expiry; the same as set($key, $value, 0).
     *
     * @throws InvalidKeyException
     */
    public function forever(
        string $key,
        mixed $value,
    ): bool;
}
