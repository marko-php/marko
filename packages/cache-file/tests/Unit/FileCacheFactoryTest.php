<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Cache\File\Factory\FileCacheFactory;
use Marko\Config\ConfigRepositoryInterface;

function createCacheConfigMock(
    string $path = '/tmp/cache',
    int $defaultTtl = 3600,
): CacheConfig {
    /** @noinspection PhpMissingParentConstructorInspection */
    $repository = new class ($path, $defaultTtl) implements ConfigRepositoryInterface
    {
        public function __construct(
            private readonly string $path,
            private readonly int $defaultTtl,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'cache.path' => $this->path,
                'cache.default_ttl' => $this->defaultTtl,
                'cache.driver' => 'file',
                default => $default,
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return in_array($key, ['cache.path', 'cache.default_ttl', 'cache.driver'], true);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->get($key) ?? $default ?? []);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [
                'cache.path' => $this->path,
                'cache.default_ttl' => $this->defaultTtl,
                'cache.driver' => 'file',
            ];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new CacheConfig($repository);
}

function cleanupFactoryTestPath(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    $files = glob($path . '/*');
    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
    rmdir($path);
}

/**
 * Write a cache entry with a past expiration timestamp.
 */
function writeExpiredFactoryCacheEntry(
    string $cachePath,
    string $key,
    mixed $value,
): void {
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
    }

    $hash = hash('xxh128', $key);
    $filePath = $cachePath . '/' . $hash . '.cache';

    $data = [
        'value' => $value,
        'expires_at' => time() - 10,
        'created_at' => time() - 20,
    ];

    file_put_contents($filePath, serialize($data));
}

it('creates FileCacheDriver instance', function () {
    $config = createCacheConfigMock();

    $factory = new FileCacheFactory($config);
    $driver = $factory->create();

    expect($driver)->toBeInstanceOf(CacheInterface::class)
        ->and($driver)->toBeInstanceOf(FileCacheDriver::class);
});

it('uses path from config', function () {
    $cachePath = sys_get_temp_dir() . '/marko-factory-test-' . uniqid();

    $config = createCacheConfigMock($cachePath);

    $factory = new FileCacheFactory($config);
    $driver = $factory->create();

    $driver->set('key', 'value');

    expect(is_dir($cachePath))->toBeTrue();

    cleanupFactoryTestPath($cachePath);
});

it('uses default ttl from config', function () {
    $cachePath = sys_get_temp_dir() . '/marko-factory-ttl-' . uniqid();

    $config = createCacheConfigMock($cachePath, 1);

    $factory = new FileCacheFactory($config);
    $driver = $factory->create();

    writeExpiredFactoryCacheEntry($cachePath, 'key', 'value');

    expect($driver->get('key'))->toBeNull();

    cleanupFactoryTestPath($cachePath);
});
