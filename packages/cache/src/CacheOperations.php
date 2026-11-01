<?php

declare(strict_types=1);

namespace Marko\Cache;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;

/**
 * The cache operations every driver shares, written once on top of the
 * CacheInterface primitives. Drivers inject it and delegate in one line, so a
 * Preference on this class changes the behaviour for every driver at once.
 *
 * Stateless: the cache to operate on is passed to each method.
 */
class CacheOperations
{
    /**
     * Return the cached value, or compute it with $callback on a miss and store it.
     *
     * A cached `null` is a hit (getItem()->isHit()), so it is returned without
     * calling $callback. Not a lock: concurrent misses each run $callback.
     *
     * @throws InvalidKeyException
     */
    public function remember(
        CacheInterface $cache,
        string $key,
        ?int $ttl,
        callable $callback,
    ): mixed {
        $item = $cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $callback();
        $cache->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get a value and delete it. Not atomic: concurrent pulls can both see the value.
     *
     * @throws InvalidKeyException
     */
    public function pull(
        CacheInterface $cache,
        string $key,
        mixed $default,
    ): mixed {
        $item = $cache->getItem($key);

        if (!$item->isHit()) {
            return $default;
        }

        $cache->delete($key);

        return $item->get();
    }

    /**
     * Store a value with no expiry (a TTL of 0).
     *
     * @throws InvalidKeyException
     */
    public function forever(
        CacheInterface $cache,
        string $key,
        mixed $value,
    ): bool {
        return $cache->set($key, $value, 0);
    }
}
