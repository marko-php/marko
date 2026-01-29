<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

function getCacheTestPath(): string
{
    return sys_get_temp_dir() . '/marko-cache-test-' . uniqid();
}

function cleanupCacheTestPath(
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

function createTestCacheConfig(
    string $path,
    int $defaultTtl = 3600,
): CacheConfig {
    $configRepo = new readonly class ($path, $defaultTtl) implements ConfigRepositoryInterface
    {
        public function __construct(
            private string $path,
            private int $defaultTtl,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'cache.path' => $this->path,
                'cache.default_ttl' => $this->defaultTtl,
                'cache.driver' => 'file',
                default => throw new ConfigNotFoundException($key),
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
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new CacheConfig($configRepo);
}

/**
 * Write a cache entry with a past expiration timestamp.
 */
function writeExpiredCacheEntry(
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
        'expires_at' => time() - 10,  // Expired 10 seconds ago
        'created_at' => time() - 20,
    ];

    file_put_contents($filePath, serialize($data));
}

beforeEach(function () {
    $this->cachePath = getCacheTestPath();
    $this->config = createTestCacheConfig($this->cachePath);
    $this->driver = new FileCacheDriver($this->config);
});

afterEach(function () {
    cleanupCacheTestPath($this->cachePath);
});

it('implements CacheInterface', function () {
    expect($this->driver)->toBeInstanceOf(CacheInterface::class);
});

it('returns default for missing key', function () {
    expect($this->driver->get('missing'))->toBeNull();
});

it('returns custom default for missing key', function () {
    expect($this->driver->get('missing', 'default'))->toBe('default');
});

it('sets and gets string value', function () {
    $this->driver->set('key', 'value');

    expect($this->driver->get('key'))->toBe('value');
});

it('sets and gets integer value', function () {
    $this->driver->set('key', 42);

    expect($this->driver->get('key'))->toBe(42);
});

it('sets and gets array value', function () {
    $value = ['name' => 'test', 'data' => [1, 2, 3]];
    $this->driver->set('key', $value);

    expect($this->driver->get('key'))->toBe($value);
});

it('sets and gets object value', function () {
    $object = new stdClass();
    $object->name = 'test';
    $this->driver->set('key', $object);

    expect($this->driver->get('key'))->toEqual($object);
});

it('sets and gets null value', function () {
    $this->driver->set('key', null);

    expect($this->driver->get('key'))->toBeNull()
        ->and($this->driver->has('key'))->toBeTrue();
});

it('returns true when setting value', function () {
    expect($this->driver->set('key', 'value'))->toBeTrue();
});

it('returns true for existing key', function () {
    $this->driver->set('key', 'value');

    expect($this->driver->has('key'))->toBeTrue();
});

it('returns false for missing key', function () {
    expect($this->driver->has('missing'))->toBeFalse();
});

it('deletes existing key', function () {
    $this->driver->set('key', 'value');
    $this->driver->delete('key');

    expect($this->driver->has('key'))->toBeFalse();
});

it('returns true when deleting existing key', function () {
    $this->driver->set('key', 'value');

    expect($this->driver->delete('key'))->toBeTrue();
});

it('returns true when deleting missing key', function () {
    expect($this->driver->delete('missing'))->toBeTrue();
});

it('clears all items', function () {
    $this->driver->set('key1', 'value1');
    $this->driver->set('key2', 'value2');

    $this->driver->clear();

    expect($this->driver->has('key1'))->toBeFalse()
        ->and($this->driver->has('key2'))->toBeFalse();
});

it('returns true when clearing', function () {
    $this->driver->set('key', 'value');

    expect($this->driver->clear())->toBeTrue();
});

it('returns true when clearing empty cache', function () {
    expect($this->driver->clear())->toBeTrue();
});

it('expires items after ttl', function () {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value');

    expect($this->driver->get('key'))->toBeNull();
});

it('does not expire items with zero ttl', function () {
    $this->driver->set('key', 'value', 0);

    expect($this->driver->get('key'))->toBe('value');
});

it('uses default ttl when not specified', function () {
    $cachePath = getCacheTestPath();
    $config = createTestCacheConfig($cachePath, 1);
    $driver = new FileCacheDriver($config);
    writeExpiredCacheEntry($cachePath, 'key', 'value');

    expect($driver->get('key'))->toBeNull();

    cleanupCacheTestPath($cachePath);
});

it('returns cache item for hit', function () {
    $this->driver->set('key', 'value');

    $item = $this->driver->getItem('key');

    expect($item)->toBeInstanceOf(CacheItemInterface::class)
        ->and($item->isHit())->toBeTrue()
        ->and($item->get())->toBe('value');
});

it('returns cache item for miss', function () {
    $item = $this->driver->getItem('missing');

    expect($item)->toBeInstanceOf(CacheItemInterface::class)
        ->and($item->isHit())->toBeFalse()
        ->and($item->get())->toBeNull();
});

it('returns cache item with expiration', function () {
    $this->driver->set('key', 'value', 3600);

    $item = $this->driver->getItem('key');

    expect($item->expiresAt())->not->toBeNull();
});

it('gets multiple keys', function () {
    $this->driver->set('key1', 'value1');
    $this->driver->set('key2', 'value2');

    $result = $this->driver->getMultiple(['key1', 'key2', 'missing']);

    expect($result)->toBe([
        'key1' => 'value1',
        'key2' => 'value2',
        'missing' => null,
    ]);
});

it('gets multiple with custom default', function () {
    $result = $this->driver->getMultiple(['missing1', 'missing2'], 'default');

    expect($result)->toBe([
        'missing1' => 'default',
        'missing2' => 'default',
    ]);
});

it('sets multiple keys', function () {
    $this->driver->setMultiple([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);

    expect($this->driver->get('key1'))->toBe('value1')
        ->and($this->driver->get('key2'))->toBe('value2');
});

it('returns true when setting multiple', function () {
    expect($this->driver->setMultiple(['key1' => 'value1']))->toBeTrue();
});

it('deletes multiple keys', function () {
    $this->driver->set('key1', 'value1');
    $this->driver->set('key2', 'value2');
    $this->driver->set('key3', 'value3');

    $this->driver->deleteMultiple(['key1', 'key2']);

    expect($this->driver->has('key1'))->toBeFalse()
        ->and($this->driver->has('key2'))->toBeFalse()
        ->and($this->driver->has('key3'))->toBeTrue();
});

it('returns true when deleting multiple', function () {
    expect($this->driver->deleteMultiple(['key1', 'key2']))->toBeTrue();
});

it('throws exception for empty key', function () {
    $this->driver->get('');
})->throws(InvalidKeyException::class, 'Cache key cannot be empty');

it('throws exception for key with invalid characters', function () {
    $this->driver->get('invalid/key');
})->throws(InvalidKeyException::class, 'Invalid cache key');

it('creates cache directory if not exists', function () {
    $newPath = sys_get_temp_dir() . '/marko-cache-new-' . uniqid();
    $config = createTestCacheConfig($newPath);
    $driver = new FileCacheDriver($config);

    $driver->set('key', 'value');

    expect(is_dir($newPath))->toBeTrue();

    cleanupCacheTestPath($newPath);
});

it('handles concurrent access safely', function () {
    $this->driver->set('key', 'initial');

    $result = $this->driver->set('key', 'updated');

    expect($result)->toBeTrue()
        ->and($this->driver->get('key'))->toBe('updated');
});

it('removes expired item on has check', function () {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value');

    expect($this->driver->has('key'))->toBeFalse();
});

it('removes expired item on getItem', function () {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value');

    $item = $this->driver->getItem('key');

    expect($item->isHit())->toBeFalse();
});
