<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Queue\Exceptions\QueueException;

/**
 * Restart signal kept in the cache under one key, stored with forever() so it never expires.
 *
 * Every worker that reads the same cache store sees the token, so with a shared store such as
 * marko/cache-redis this works for workers on hosts that share no filesystem. Chosen with
 * queue.restart_signal_driver = cache; RestartSignalFactory checks marko/cache is installed first.
 */
class CacheRestartSignal implements RestartSignalInterface
{
    /**
     * The cache key that holds the restart token.
     */
    public const string KEY = 'queue.restart_signal';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Store a new random token with no expiry.
     *
     * @throws QueueException|InvalidKeyException
     */
    public function request(): void
    {
        if (!$this->cache->forever(self::KEY, bin2hex(random_bytes(16)))) {
            throw QueueException::restartSignalCacheNotWritable(self::KEY);
        }
    }

    /**
     * @throws QueueException|InvalidKeyException
     */
    public function current(): ?string
    {
        $token = $this->cache->get(self::KEY);

        if ($token === null) {
            return null;
        }

        if (!is_string($token) || $token === '') {
            throw QueueException::restartSignalCacheValueInvalid(self::KEY, get_debug_type($token));
        }

        return $token;
    }
}
