<?php

declare(strict_types=1);

namespace Marko\Cache\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class CacheConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function driver(): string
    {
        return $this->config->getString('cache.driver', 'file');
    }

    public function path(): string
    {
        return $this->config->getString('cache.path', 'storage/cache');
    }

    public function defaultTtl(): int
    {
        return $this->config->getInt('cache.default_ttl', 3600);
    }
}
