<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

function createArrayCacheTestConfig(
    int $defaultTtl = 3600,
): CacheConfig {
    $configRepo = new readonly class ($defaultTtl) implements ConfigRepositoryInterface
    {
        public function __construct(
            private int $defaultTtl,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'cache.path' => '/tmp/cache',
                'cache.default_ttl' => $this->defaultTtl,
                'cache.driver' => 'array',
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

beforeEach(function () {
    $this->config = createArrayCacheTestConfig();
    $this->driver = new ArrayCacheDriver($this->config);
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

    expect($this->driver->get('key'))->toBe($object);
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

it('does not expire items with zero ttl', function () {
    $this->driver->set('key', 'value', 0);

    expect($this->driver->get('key'))->toBe('value');
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

it('overwrites existing value', function () {
    $this->driver->set('key', 'initial');
    $this->driver->set('key', 'updated');

    expect($this->driver->get('key'))->toBe('updated');
});

it('stores same object reference', function () {
    $object = new stdClass();
    $object->name = 'test';
    $this->driver->set('key', $object);

    // Array cache stores the actual reference, not a serialized copy
    expect($this->driver->get('key'))->toBe($object);
});

it('is isolated per instance', function () {
    $driver1 = new ArrayCacheDriver($this->config);
    $driver2 = new ArrayCacheDriver($this->config);

    $driver1->set('key', 'value1');

    expect($driver2->has('key'))->toBeFalse();
});
