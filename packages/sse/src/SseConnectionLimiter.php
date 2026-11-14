<?php

declare(strict_types=1);

namespace Marko\Sse;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Sse\Exceptions\SseException;
use Random\RandomException;

/**
 * Caps concurrent SSE streams across all PHP processes (config `sse.max_connections`).
 *
 * Each connection holds one of N slot keys in the shared cache. A slot is taken when
 * increment() returns 1 (the key did not exist) and released with delete(). Slot keys
 * carry a TTL, so a slot held by a worker that died is reclaimed when its TTL expires.
 * Only increment() return values are used — never get().
 */
readonly class SseConnectionLimiter
{
    private const string SLOT_KEY_PREFIX = 'sse.connection_slot.';

    /**
     * Cache drivers whose storage lives inside one PHP process and so cannot coordinate workers.
     *
     * @var list<string>
     */
    private const array PROCESS_LOCAL_CACHE_DRIVERS = [
        'Marko\Cache\Memory\Driver\ArrayCacheDriver',
    ];

    /**
     * @throws SseException
     */
    public function __construct(
        private CacheInterface $cache,
        private ?int $maxConnections,
        private int $retryAfter,
    ) {
        if ($this->maxConnections === null) {
            return;
        }

        if ($this->maxConnections < 1) {
            throw SseException::invalidMaxConnections($this->maxConnections);
        }

        foreach (self::PROCESS_LOCAL_CACHE_DRIVERS as $driverClass) {
            if (is_a($this->cache, $driverClass)) {
                throw SseException::processLocalCache($this->cache::class);
            }
        }
    }

    public function isLimited(): bool
    {
        return $this->maxConnections !== null;
    }

    /**
     * Seconds a rejected client should wait before reconnecting.
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Take a free slot, held for at most $ttl seconds.
     *
     * @return int|null The slot number, or null when every slot is taken (or the guard is disabled)
     * @throws InvalidKeyException|RandomException
     */
    public function acquire(
        int $ttl,
    ): ?int {
        if ($this->maxConnections === null) {
            return null;
        }

        // Start at a random slot so concurrent requests don't all contend for slot 0.
        $offset = random_int(0, $this->maxConnections - 1);

        for ($i = 0; $i < $this->maxConnections; $i++) {
            $slot = ($offset + $i) % $this->maxConnections;

            if ($this->cache->increment($this->slotKey($slot), $ttl) === 1) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @throws InvalidKeyException
     */
    public function release(
        int $slot,
    ): void {
        $this->cache->delete($this->slotKey($slot));
    }

    private function slotKey(
        int $slot,
    ): string {
        return self::SLOT_KEY_PREFIX . $slot;
    }
}
