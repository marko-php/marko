<?php

declare(strict_types=1);

namespace Marko\Cache\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Path\ProjectPaths;

readonly class CacheConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private ?ProjectPaths $paths = null,
    ) {}

    public function driver(): string
    {
        return $this->config->getString('cache.driver');
    }

    /**
     * A relative cache.path resolves against the project root, never getcwd():
     * FPM, CGI and mod_php chdir into public/ before running the front controller.
     */
    public function path(): string
    {
        $path = $this->config->getString('cache.path');

        if ($this->paths === null || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->paths->base . '/' . $path;
    }

    public function defaultTtl(): int
    {
        return $this->config->getInt('cache.default_ttl');
    }
}
