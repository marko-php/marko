<?php

declare(strict_types=1);

namespace Marko\Cache\File\Factory;

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Driver\FileCacheDriver;

readonly class FileCacheFactory
{
    public function __construct(
        private CacheConfig $config,
    ) {}

    public function create(): CacheInterface
    {
        return new FileCacheDriver(
            path: $this->config->path(),
            defaultTtl: $this->config->defaultTtl(),
        );
    }
}
