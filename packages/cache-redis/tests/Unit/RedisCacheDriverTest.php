<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Cache\Redis\Exceptions\TamperedCacheValueException;
use Marko\Cache\Redis\RedisConnection;
use Marko\Cache\Redis\Signer\CacheValueSigner;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Testing\Fake\FakeConfigRepository;
use Predis\Client;
use Predis\ClientInterface;

/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
class MockRedisClient extends Client
{
    /** @var array<string, string> */
    public array $storage = [];

    /** @var array<string, int> */
    public array $ttls = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {}

    public function get(
        $key,
    ): ?string {
        return $this->storage[$key] ?? null;
    }

    public function set(
        $key,
        $value,
        ...$args,
    ): mixed {
        $this->storage[$key] = $value;

        return 'OK';
    }

    public function setex(
        $key,
        $seconds,
        $value,
    ): mixed {
        $this->storage[$key] = $value;
        $this->ttls[$key] = $seconds;

        return 'OK';
    }

    public function exists(
        ...$keys,
    ): int {
        $count = 0;

        foreach ($keys as $key) {
            if (isset($this->storage[$key])) {
                $count++;
            }
        }

        return $count;
    }

    public function del(
        ...$keys,
    ): int {
        $count = 0;
        $flatKeys = [];

        foreach ($keys as $key) {
            if (is_array($key)) {
                $flatKeys = array_merge($flatKeys, $key);
            } else {
                $flatKeys[] = $key;
            }
        }

        foreach ($flatKeys as $key) {
            if (isset($this->storage[$key])) {
                unset($this->storage[$key], $this->ttls[$key]);
                $count++;
            }
        }

        return $count;
    }

    public function keys(
        $pattern,
    ): array {
        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
        $regex = str_replace('\\.*', '.*', $regex);

        return array_values(array_filter(
            array_keys($this->storage),
            fn ($key) => preg_match($regex, $key) === 1,
        ));
    }

    public function ttl(
        $key,
    ): int {
        if (!isset($this->storage[$key])) {
            return -2;
        }

        return $this->ttls[$key] ?? -1;
    }

    public function incr(
        $key,
    ): int {
        $current = (int) ($this->storage[$key] ?? 0);
        $new = $current + 1;
        $this->storage[$key] = (string) $new;

        return $new;
    }

    public function expire(
        $key,
        $seconds,
    ): int {
        if (!isset($this->storage[$key])) {
            return 0;
        }

        $this->ttls[$key] = $seconds;

        return 1;
    }
}

function createMockClient(): MockRedisClient
{
    return new MockRedisClient();
}

function createCacheConfig(
    int $defaultTtl = 3600,
): CacheConfig {
    return new CacheConfig(new FakeConfigRepository([
        'cache.path' => '/tmp/cache',
        'cache.default_ttl' => $defaultTtl,
        'cache.driver' => 'redis',
    ]));
}

function createSigner(
    string $key = 'test-signing-key',
): CacheValueSigner {
    return new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
        'encryption.key' => $key,
    ])));
}

function createDriver(
    ?MockRedisClient $mockClient = null,
    int $defaultTtl = 3600,
    string $signingKey = 'test-signing-key',
): RedisCacheDriver {
    $mockClient ??= createMockClient();
    $connection = new class ($mockClient) extends RedisConnection
    {
        public function __construct(
            private readonly ClientInterface $mockClient,
        ) {
            parent::__construct();
        }

        protected function createClient(): ClientInterface
        {
            return $this->mockClient;
        }
    };
    $config = createCacheConfig($defaultTtl);

    return new RedisCacheDriver($connection, $config, createSigner($signingKey));
}

describe('RedisCacheDriver', function (): void {
    beforeEach(function (): void {
        $this->mockClient = createMockClient();
        $this->driver = createDriver($this->mockClient);
    });

    it('implements CacheInterface', function (): void {
        expect($this->driver)->toBeInstanceOf(CacheInterface::class);
    });

    it('returns default for missing key', function (): void {
        expect($this->driver->get('missing'))->toBeNull();
    });

    it('returns custom default for missing key', function (): void {
        expect($this->driver->get('missing', 'default'))->toBe('default');
    });

    it('sets and gets string value', function (): void {
        $this->driver->set('key', 'value');

        expect($this->driver->get('key'))->toBe('value');
    });

    it('sets and gets integer value', function (): void {
        $this->driver->set('key', 42);

        expect($this->driver->get('key'))->toBe(42);
    });

    it('sets and gets array value', function (): void {
        $this->driver->set('key', ['a' => 1]);

        expect($this->driver->get('key'))->toBe(['a' => 1]);
    });

    it('sets and gets null value', function (): void {
        $this->driver->set('key', null);

        expect($this->driver->get('key'))->toBeNull()
            ->and($this->driver->has('key'))->toBeTrue();
    });

    it('returns true when setting value', function (): void {
        expect($this->driver->set('key', 'value'))->toBeTrue();
    });

    it('returns true for existing key', function (): void {
        $this->driver->set('key', 'value');

        expect($this->driver->has('key'))->toBeTrue();
    });

    it('returns false for missing key', function (): void {
        expect($this->driver->has('missing'))->toBeFalse();
    });

    it('deletes existing key', function (): void {
        $this->driver->set('key', 'value');
        $this->driver->delete('key');

        expect($this->driver->has('key'))->toBeFalse();
    });

    it('returns true when deleting existing key', function (): void {
        $this->driver->set('key', 'value');

        expect($this->driver->delete('key'))->toBeTrue();
    });

    it('returns true when deleting missing key', function (): void {
        expect($this->driver->delete('missing'))->toBeTrue();
    });

    it('clears all prefixed keys', function (): void {
        $this->driver->set('key1', 'value1');
        $this->driver->set('key2', 'value2');

        $this->driver->clear();

        expect($this->driver->has('key1'))->toBeFalse()
            ->and($this->driver->has('key2'))->toBeFalse();
    });

    it('returns true when clearing', function (): void {
        expect($this->driver->clear())->toBeTrue();
    });

    it('sets value with TTL', function (): void {
        $this->driver->set('key', 'val', 60);

        expect($this->mockClient->ttls['marko:cache:key'])->toBe(60);
    });

    it('sets persistent value with zero TTL', function (): void {
        $this->driver->set('key', 'val', 0);

        expect(isset($this->mockClient->storage['marko:cache:key']))->toBeTrue()
            ->and(isset($this->mockClient->ttls['marko:cache:key']))->toBeFalse();
    });

    it('uses default TTL when not specified', function (): void {
        $this->driver->set('key', 'val');

        expect($this->mockClient->ttls['marko:cache:key'])->toBe(3600);
    });

    it('throws exception for empty key', function (): void {
        $this->driver->get('');
    })->throws(InvalidKeyException::class, 'Cache key cannot be empty');

    it('throws exception for key with invalid characters', function (): void {
        $this->driver->get('invalid/key');
    })->throws(InvalidKeyException::class, 'Invalid cache key');

    it('overwrites existing value', function (): void {
        $this->driver->set('key', 'initial');
        $this->driver->set('key', 'updated');

        expect($this->driver->get('key'))->toBe('updated');
    });

    it('prefixes keys in Redis', function (): void {
        $this->driver->set('mykey', 'val');

        expect(isset($this->mockClient->storage['marko:cache:mykey']))->toBeTrue();
    });

    it('returns cache item for hit', function (): void {
        $this->driver->set('key', 'value');

        $item = $this->driver->getItem('key');

        expect($item)->toBeInstanceOf(CacheItemInterface::class)
            ->and($item->isHit())->toBeTrue()
            ->and($item->get())->toBe('value');
    });

    it('returns cache item for miss', function (): void {
        $item = $this->driver->getItem('missing');

        expect($item)->toBeInstanceOf(CacheItemInterface::class)
            ->and($item->isHit())->toBeFalse()
            ->and($item->get())->toBeNull();
    });

    it('returns cache item with expiration', function (): void {
        $this->driver->set('key', 'value', 3600);

        $item = $this->driver->getItem('key');

        expect($item->expiresAt())->not->toBeNull();
    });

    it('returns cache item without expiration for persistent key', function (): void {
        $this->driver->set('key', 'value', 0);

        $item = $this->driver->getItem('key');

        expect($item->isHit())->toBeTrue()
            ->and($item->expiresAt())->toBeNull();
    });

    it('gets multiple keys', function (): void {
        $this->driver->set('key1', 'value1');
        $this->driver->set('key2', 'value2');

        $result = $this->driver->getMultiple(['key1', 'key2', 'missing']);

        expect($result)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'missing' => null,
        ]);
    });

    it('gets multiple with custom default', function (): void {
        $result = $this->driver->getMultiple(['missing1', 'missing2'], 'default');

        expect($result)->toBe([
            'missing1' => 'default',
            'missing2' => 'default',
        ]);
    });

    it('sets multiple keys', function (): void {
        $this->driver->setMultiple(['key1' => 'value1', 'key2' => 'value2']);

        expect($this->driver->get('key1'))->toBe('value1')
            ->and($this->driver->get('key2'))->toBe('value2');
    });

    it('returns true when setting multiple', function (): void {
        expect($this->driver->setMultiple(['key1' => 'value1', 'key2' => 'value2']))->toBeTrue();
    });

    it('deletes multiple keys', function (): void {
        $this->driver->set('key1', 'value1');
        $this->driver->set('key2', 'value2');
        $this->driver->set('key3', 'value3');

        $this->driver->deleteMultiple(['key1', 'key2']);

        expect($this->driver->has('key1'))->toBeFalse()
            ->and($this->driver->has('key2'))->toBeFalse()
            ->and($this->driver->has('key3'))->toBeTrue();
    });

    it('returns true when deleting multiple', function (): void {
        expect($this->driver->deleteMultiple(['key1', 'key2']))->toBeTrue();
    });

    it('returns 1 when incrementing a key that does not yet exist (redis driver)', function (): void {
        expect($this->driver->increment('counter', 60))->toBe(1);
    });

    it('returns the incremented value on a subsequent increment (redis driver)', function (): void {
        $this->driver->increment('counter', 60);

        expect($this->driver->increment('counter', 60))->toBe(2);
    });

    it('sets an expiry on the key when incrementing (redis driver)', function (): void {
        $this->driver->increment('counter', 60);

        expect($this->mockClient->ttls['marko:cache:counter'])->toBe(60);
    });

    it('does not reset the ttl on a subsequent increment (redis driver)', function (): void {
        $this->driver->increment('counter', 60);

        // Simulate TTL not being reset by verifying expire() is only called when value is 1
        // Set ttl to a different value to detect if it was changed
        $this->mockClient->ttls['marko:cache:counter'] = 999;

        $this->driver->increment('counter', 60);

        expect($this->mockClient->ttls['marko:cache:counter'])->toBe(999);
    });

    it('stores an HMAC-signed envelope when setting a redis cache value', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key', 'value');

        $stored = $mockClient->storage['marko:cache:key'];

        expect(strlen($stored))->toBeGreaterThan(65)
            ->and(substr($stored, 64, 1))->toBe('.');
    });

    it('returns the original value when the stored envelope HMAC verifies', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key', 'hello world');

        expect($driver->get('key'))->toBe('hello world');
    });

    it('rejects a redis value whose HMAC does not verify before unserializing it', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $serialized = serialize('legitimate value');
        $fakeHmac = str_repeat('a', 64);
        $mockClient->storage['marko:cache:key'] = $fakeHmac . '.' . $serialized;

        expect(fn () => $driver->get('key'))
            ->toThrow(TamperedCacheValueException::class);
    });

    it('does not unserialize a redis value that has been tampered with', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key', 'original value');

        $stored = $mockClient->storage['marko:cache:key'];
        $hmac = substr($stored, 0, 64);
        $mockClient->storage['marko:cache:key'] = $hmac . '.' . serialize('tampered value');

        expect(fn () => $driver->get('key'))
            ->toThrow(TamperedCacheValueException::class);
    });

    it('throws loudly when the signing key is empty', function (): void {
        $driver = createDriver(signingKey: '');

        expect(fn () => $driver->set('key', 'value'))
            ->toThrow(TamperedCacheValueException::class);
    });

    it('rejects a stored value that has no envelope framing (legacy/unsigned data)', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $mockClient->storage['marko:cache:key'] = serialize('unsigned value');

        expect(fn () => $driver->get('key'))
            ->toThrow(TamperedCacheValueException::class);
    });

    it('does not route increment() integer counters through the HMAC envelope', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->increment('counter', 60);

        expect($mockClient->storage['marko:cache:counter'])->toBe('1');
    });

    it('round-trips a legitimate value through set and get', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $value = ['nested' => ['array' => true, 'count' => 42]];
        $driver->set('complex', $value);

        expect($driver->get('complex'))->toBe($value);
    });
});
