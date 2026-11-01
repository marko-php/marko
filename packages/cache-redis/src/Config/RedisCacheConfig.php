<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class RedisCacheConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The marko/redis connection name (a key of redis.connections) the cache uses.
     */
    public function connection(): string
    {
        return $this->config->getString('cache-redis.connection');
    }

    /**
     * The prefix added to every cache key.
     */
    public function prefix(): string
    {
        return $this->config->getString('cache-redis.prefix');
    }
}
