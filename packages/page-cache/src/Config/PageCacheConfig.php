<?php

declare(strict_types=1);

namespace Marko\PageCache\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class PageCacheConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function driver(): string
    {
        return $this->configRepository->getString('page-cache.driver');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function path(): string
    {
        return $this->configRepository->getString('page-cache.path');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function defaultTtl(): int
    {
        return $this->configRepository->getInt('page-cache.default_ttl');
    }

    /**
     * @return array<int>
     *
     * @throws ConfigNotFoundException
     */
    public function cacheableStatusCodes(): array
    {
        return array_map(
            static fn (mixed $code): int => (int) $code,
            $this->configRepository->getArray('page-cache.cacheable_status_codes'),
        );
    }

    /**
     * @return array<string>
     *
     * @throws ConfigNotFoundException
     */
    public function cacheableMethods(): array
    {
        return array_map(
            static fn (mixed $method): string => strtoupper((string) $method),
            $this->configRepository->getArray('page-cache.cacheable_methods'),
        );
    }
}
