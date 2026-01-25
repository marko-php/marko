<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Config\ConfigRepositoryInterface;

function createCacheConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->data[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->data[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->data[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->data[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->data[$key] ?? $default ?? []);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('returns driver from config', function () {
    $config = new CacheConfig(createCacheConfigRepository([
        'cache.driver' => 'redis',
    ]));

    expect($config->driver())->toBe('redis');
});

it('returns default driver when not configured', function () {
    $config = new CacheConfig(createCacheConfigRepository());

    expect($config->driver())->toBe('file');
});

it('returns path from config', function () {
    $config = new CacheConfig(createCacheConfigRepository([
        'cache.path' => '/var/cache',
    ]));

    expect($config->path())->toBe('/var/cache');
});

it('returns default path when not configured', function () {
    $config = new CacheConfig(createCacheConfigRepository());

    expect($config->path())->toBe('storage/cache');
});

it('returns default ttl from config', function () {
    $config = new CacheConfig(createCacheConfigRepository([
        'cache.default_ttl' => 7200,
    ]));

    expect($config->defaultTtl())->toBe(7200);
});

it('returns default ttl when not configured', function () {
    $config = new CacheConfig(createCacheConfigRepository());

    expect($config->defaultTtl())->toBe(3600);
});
