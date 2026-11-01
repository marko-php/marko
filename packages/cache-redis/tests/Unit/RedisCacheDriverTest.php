<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Redis\RedisConnectionInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Predis\Client;
use Predis\ClientInterface;

readonly class MockPipelineRecorder
{
    public function __construct(
        private MockRedisClient $client,
    ) {}

    public function setex(
        string $key,
        int $seconds,
        string $value,
    ): void {
        $this->client->storage[$key] = $value;
        $this->client->ttls[$key] = $seconds;
    }

    public function set(
        string $key,
        string $value,
    ): void {
        $this->client->storage[$key] = $value;
    }
}

/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
class MockRedisClient extends Client
{
    /** @var array<string, string> */
    public array $storage = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public int $mgetCount = 0;

    public int $pipelineCount = 0;

    public int $delCount = 0;

    public int $evalCount = 0;

    public int $incrCount = 0;

    public int $expireCount = 0;

    public ?string $lastEvalScript = null;

    public int $scanCount = 0;

    public int $scanPageSize = 2;

    public string $lastScanPattern = '';

    /** @var list<string> */
    private array $scanSnapshot = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {}

    public function get(
        $key,
    ): ?string {
        return $this->storage[$key] ?? null;
    }

    /** @var list<list<mixed>> */
    public array $setArgs = [];

    /**
     * Emulates SET with the EX and NX options: NX refuses an existing key
     * (Redis replies nil) and EX records the TTL.
     */
    public function set(
        $key,
        $value,
        ...$args,
    ): mixed {
        $this->setArgs[] = $args;
        $options = array_map(fn ($arg) => is_string($arg) ? strtoupper($arg) : $arg, $args);

        if (in_array('NX', $options, true) && isset($this->storage[$key])) {
            return null;
        }

        $this->storage[$key] = $value;
        unset($this->ttls[$key]);
        $exIndex = array_search('EX', $options, true);

        if ($exIndex !== false) {
            $this->ttls[$key] = (int) $args[$exIndex + 1];
        }

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
        $this->delCount++;
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

    public function mget(
        ...$keys,
    ): array {
        $this->mgetCount++;
        $flatKeys = [];

        foreach ($keys as $key) {
            if (is_array($key)) {
                $flatKeys = array_merge($flatKeys, $key);
            } else {
                $flatKeys[] = $key;
            }
        }

        return array_map(fn ($k) => $this->storage[$k] ?? null, $flatKeys);
    }

    public function pipeline(
        ...$arguments,
    ): mixed {
        $this->pipelineCount++;

        $callback = $arguments[0] ?? null;

        if (is_callable($callback)) {
            $recorder = new MockPipelineRecorder($this);
            $callback($recorder);
        }

        return [];
    }

    /**
     * KEYS blocks the Redis server on a large keyspace, so the driver must never call it.
     */
    public function keys(
        $pattern,
    ): array {
        throw new RuntimeException('RedisCacheDriver must not call KEYS; use SCAN.');
    }

    /**
     * Emulates SCAN: walks a snapshot of the keyspace $scanPageSize keys per call,
     * returning the MATCH hits from each page and a cursor of "0" once the walk is
     * complete. Like Redis, keys deleted mid-walk do not cause others to be skipped.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function scan(
        $cursor,
        ?array $options = null,
    ): array {
        $this->scanCount++;
        $this->lastScanPattern = $options['MATCH'] ?? '*';

        if ((string) $cursor === '0') {
            $this->scanSnapshot = array_keys($this->storage);
        }

        $page = array_slice($this->scanSnapshot, (int) $cursor, $this->scanPageSize);
        $next = (int) $cursor + $this->scanPageSize;

        return [
            $next >= count($this->scanSnapshot) ? '0' : (string) $next,
            array_values(array_filter($page, fn ($key) => fnmatch($this->lastScanPattern, $key))),
        ];
    }

    public function ttl(
        $key,
    ): int {
        if (!isset($this->storage[$key])) {
            return -2;
        }

        return $this->ttls[$key] ?? -1;
    }

    /**
     * Emulates the driver's atomic increment Lua script: INCR, then EXPIRE when
     * the counter was just created or has no TTL. Records the call so tests can
     * assert the increment happened in one round trip.
     */
    public function eval(
        $script,
        $numkeys,
        ...$arguments,
    ): mixed {
        $this->evalCount++;
        $this->lastEvalScript = $script;

        $key = $arguments[0];
        $ttl = (int) $arguments[1];
        $current = (int) ($this->storage[$key] ?? 0);
        $new = $current + 1;
        $this->storage[$key] = (string) $new;

        if ($ttl > 0 && ($new === 1 || !isset($this->ttls[$key]))) {
            $this->ttls[$key] = $ttl;
        }

        return $new;
    }

    public function incr(
        $key,
    ): int {
        $this->incrCount++;
        $current = (int) ($this->storage[$key] ?? 0);
        $new = $current + 1;
        $this->storage[$key] = (string) $new;

        return $new;
    }

    public function expire(
        $key,
        $seconds,
    ): int {
        $this->expireCount++;

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
    ?FakeClock $clock = null,
    string $prefix = 'marko:cache:',
    ?CacheOperations $operations = null,
): RedisCacheDriver {
    $mockClient ??= createMockClient();
    $config = createCacheConfig($defaultTtl);

    return new RedisCacheDriver(
        createRedisCacheConnectionFactory(createRedisCacheMockConnection($mockClient)),
        createRedisCacheDriverConfig(prefix: $prefix),
        $config,
        createSigner($signingKey),
        $clock ?? new FakeClock(),
        $operations ?? new CacheOperations(),
    );
}

function createRedisCacheMockConnection(
    ClientInterface $client,
): RedisConnectionInterface {
    return new readonly class ($client) implements RedisConnectionInterface
    {
        public function __construct(
            private ClientInterface $mockClient,
        ) {}

        public function client(): ClientInterface
        {
            return $this->mockClient;
        }

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }
    };
}

/**
 * A factory that knows one connection name and hands out the given connection for it.
 */
function createRedisCacheConnectionFactory(
    RedisConnectionInterface $connection,
    string $name = 'default',
): RedisConnectionFactoryInterface {
    return new readonly class ($connection, $name) implements RedisConnectionFactoryInterface
    {
        public function __construct(
            private RedisConnectionInterface $redisConnection,
            private string $name,
        ) {}

        public function connection(
            string $name,
        ): RedisConnectionInterface {
            if ($name !== $this->name) {
                throw new RuntimeException("Unexpected Redis connection '$name'");
            }

            return $this->redisConnection;
        }
    };
}

function createRedisCacheDriverConfig(
    string $connection = 'default',
    string $prefix = 'marko:cache:',
): RedisCacheConfig {
    return new RedisCacheConfig(new FakeConfigRepository([
        'cache-redis.connection' => $connection,
        'cache-redis.prefix' => $prefix,
    ]));
}

describe('RedisCacheDriver', function (): void {
    beforeEach(function (): void {
        $this->mockClient = createMockClient();
        $this->driver = createDriver($this->mockClient);
    });

    it('stores through the marko/redis connection named by cache-redis.connection', function (): void {
        $mockClient = createMockClient();
        $driver = new RedisCacheDriver(
            createRedisCacheConnectionFactory(createRedisCacheMockConnection($mockClient), name: 'cache'),
            createRedisCacheDriverConfig(connection: 'cache', prefix: 'app:'),
            createCacheConfig(),
            createSigner(),
            new FakeClock(),
            new CacheOperations(),
        );

        $driver->set('key', 'value');

        expect($mockClient->storage)->toHaveKey('app:key')
            ->and($driver->get('key'))->toBe('value');
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

    it('clears with SCAN across every page and removes only prefixed keys', function (): void {
        $this->mockClient->storage['other:app:key'] = 'keep';
        $this->mockClient->storage['marko:cachex'] = 'keep';

        foreach (range(1, 5) as $i) {
            $this->driver->set("key$i", "value$i");
        }

        $this->mockClient->storage['session:abc'] = 'keep';

        $this->driver->clear();

        expect(array_keys($this->mockClient->storage))->toBe(['other:app:key', 'marko:cachex', 'session:abc'])
            ->and($this->mockClient->scanCount)->toBe(4)
            ->and($this->mockClient->lastScanPattern)->toBe('marko:cache:*');
    });

    it('escapes glob characters in the prefix when clearing', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient, prefix: 'app[1]*:');

        $driver->set('key', 'value');
        $mockClient->storage['app1x:other'] = 'keep';

        $driver->clear();

        expect(array_keys($mockClient->storage))->toBe(['app1x:other'])
            ->and($mockClient->lastScanPattern)->toBe('app\[1\]\*:*');
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

    it('reports the item expiry as the clock time plus the remaining redis ttl', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $driver = createDriver($this->mockClient, clock: $clock);
        $driver->set('key', 'value', 3600);
        $this->mockClient->ttls['marko:cache:key'] = 1200;

        $expiresAt = $driver->getItem('key')->expiresAt();

        expect($expiresAt)->not->toBeNull()
            ->and($expiresAt->getTimestamp())->toBe($clock->now()->getTimestamp() + 1200);
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

    it('rejects a signed value copied from one cache key to another', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('role-alice', 'admin');
        $driver->set('role-bob', 'guest');
        $mockClient->storage['marko:cache:role-bob'] = $mockClient->storage['marko:cache:role-alice'];

        expect(fn () => $driver->get('role-bob'))
            ->toThrow(TamperedCacheValueException::class)
            ->and(fn () => $driver->getItem('role-bob'))
            ->toThrow(TamperedCacheValueException::class)
            ->and(fn () => $driver->getMultiple(['role-alice', 'role-bob']))
            ->toThrow(TamperedCacheValueException::class)
            ->and($driver->get('role-alice'))->toBe('admin');
    });

    it('binds the MAC to the prefixed key rather than the bare key', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key', 'value');

        expect(createSigner()->unwrap($mockClient->storage['marko:cache:key'], 'marko:cache:key'))
            ->toBe(serialize('value'))
            ->and(createSigner()->unwrap($mockClient->storage['marko:cache:key'], 'key'))->toBeNull();
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

    it('returns values for all requested keys via getMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key1', 'value1');
        $driver->set('key2', 'value2');

        $result = $driver->getMultiple(['key1', 'key2']);

        expect($result)->toBe(['key1' => 'value1', 'key2' => 'value2']);
    });

    it('returns the default for missing keys in getMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $result = $driver->getMultiple(['missing1', 'missing2'], 'fallback');

        expect($result)->toBe(['missing1' => 'fallback', 'missing2' => 'fallback']);
    });

    it('preserves input key order in the getMultiple result', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('z', 'z-val');
        $driver->set('a', 'a-val');

        $result = $driver->getMultiple(['z', 'a']);

        expect(array_keys($result))->toBe(['z', 'a']);
    });

    it('issues a single MGET for getMultiple instead of one get per key', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key1', 'v1');
        $driver->set('key2', 'v2');

        $mockClient->mgetCount = 0;

        $driver->getMultiple(['key1', 'key2']);

        expect($mockClient->mgetCount)->toBe(1);
    });

    it('verifies the HMAC envelope on each value read by getMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $mockClient->storage['marko:cache:tampered'] = str_repeat('a', 64) . '.' . serialize('bad');

        expect(fn () => $driver->getMultiple(['tampered']))
            ->toThrow(TamperedCacheValueException::class);
    });

    it('stores all pairs via setMultiple with the given TTL', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->setMultiple(['key1' => 'v1', 'key2' => 'v2'], 120);

        expect($driver->get('key1'))->toBe('v1')
            ->and($driver->get('key2'))->toBe('v2')
            ->and($mockClient->ttls['marko:cache:key1'])->toBe(120)
            ->and($mockClient->ttls['marko:cache:key2'])->toBe(120);
    });

    it('applies the default TTL in setMultiple when none is given', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient, defaultTtl: 3600);

        $driver->setMultiple(['key1' => 'v1']);

        expect($mockClient->ttls['marko:cache:key1'])->toBe(3600);
    });

    it('stores persistent pairs without a TTL when setMultiple TTL is zero', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->setMultiple(['key1' => 'v1'], 0);

        expect(isset($mockClient->storage['marko:cache:key1']))->toBeTrue()
            ->and(isset($mockClient->ttls['marko:cache:key1']))->toBeFalse();
    });

    it('issues the setMultiple writes in a single pipeline', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $mockClient->pipelineCount = 0;

        $driver->setMultiple(['key1' => 'v1', 'key2' => 'v2']);

        expect($mockClient->pipelineCount)->toBe(1);
    });

    it('stores each setMultiple value as a signed HMAC envelope', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->setMultiple(['key1' => 'v1']);

        $stored = $mockClient->storage['marko:cache:key1'];

        expect(strlen($stored))->toBeGreaterThan(65)
            ->and(substr($stored, 64, 1))->toBe('.');
    });

    it('round-trips values written by setMultiple back through getMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->setMultiple(['alpha' => 'hello', 'beta' => [1, 2, 3]]);

        $result = $driver->getMultiple(['alpha', 'beta']);

        expect($result)->toBe(['alpha' => 'hello', 'beta' => [1, 2, 3]]);
    });

    it('deletes all given keys via deleteMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key1', 'v1');
        $driver->set('key2', 'v2');
        $driver->set('key3', 'v3');

        $driver->deleteMultiple(['key1', 'key2']);

        expect($driver->has('key1'))->toBeFalse()
            ->and($driver->has('key2'))->toBeFalse()
            ->and($driver->has('key3'))->toBeTrue();
    });

    it('issues a single variadic DEL for deleteMultiple', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        $driver->set('key1', 'v1');
        $driver->set('key2', 'v2');

        $mockClient->delCount = 0;

        $driver->deleteMultiple(['key1', 'key2']);

        expect($mockClient->delCount)->toBe(1);
    });

    it('validates every key in the multi-key operations', function (): void {
        $mockClient = createMockClient();
        $driver = createDriver($mockClient);

        expect(fn () => $driver->getMultiple(['valid', 'invalid/key']))
            ->toThrow(InvalidKeyException::class)
            ->and(fn () => $driver->setMultiple(['valid' => 'v', 'invalid/key' => 'v']))
            ->toThrow(InvalidKeyException::class)
            ->and(fn () => $driver->deleteMultiple(['valid', 'invalid/key']))
            ->toThrow(InvalidKeyException::class);
    });

    describe('increment() counters', function (): void {
        it('returns an int from get() for a key written by increment()', function (): void {
            $this->driver->increment('counter', 60);
            $this->driver->increment('counter', 60);

            expect($this->driver->get('counter'))->toBe(2);
        });

        it('returns an int cache item from getItem() for a key written by increment()', function (): void {
            $this->driver->increment('counter', 60);

            $item = $this->driver->getItem('counter');

            expect($item->isHit())->toBeTrue()
                ->and($item->get())->toBe(1)
                ->and($item->expiresAt())->not->toBeNull();
        });

        it('returns ints from getMultiple() for keys written by increment()', function (): void {
            $this->driver->increment('a', 60);
            $this->driver->increment('b', 60);
            $this->driver->increment('b', 60);
            $this->driver->set('c', 'text');

            expect($this->driver->getMultiple(['a', 'b', 'c']))
                ->toBe(['a' => 1, 'b' => 2, 'c' => 'text']);
        });

        it('returns a negative integer counter as an int', function (): void {
            $this->mockClient->storage['marko:cache:counter'] = '-3';

            expect($this->driver->get('counter'))->toBe(-3);
        });

        it('still throws TamperedCacheValueException for a tampered non-integer value', function (): void {
            $this->mockClient->storage['marko:cache:counter'] = '12abc';

            expect(fn () => $this->driver->get('counter'))
                ->toThrow(TamperedCacheValueException::class)
                ->and(fn () => $this->driver->getItem('counter'))
                ->toThrow(TamperedCacheValueException::class)
                ->and(fn () => $this->driver->getMultiple(['counter']))
                ->toThrow(TamperedCacheValueException::class);
        });

        it('does not treat an integer followed by a newline as a counter', function (): void {
            $this->mockClient->storage['marko:cache:counter'] = "12\n";

            expect(fn () => $this->driver->get('counter'))
                ->toThrow(TamperedCacheValueException::class);
        });

        it('increments and sets the ttl in a single atomic script call', function (): void {
            $this->driver->increment('counter', 60);

            expect($this->mockClient->evalCount)->toBe(1)
                ->and($this->mockClient->incrCount)->toBe(0)
                ->and($this->mockClient->expireCount)->toBe(0)
                ->and($this->mockClient->lastEvalScript)->toContain('INCR')
                ->and($this->mockClient->lastEvalScript)->toContain('EXPIRE')
                ->and($this->mockClient->ttls['marko:cache:counter'])->toBe(60);
        });

        it('restores a missing ttl on a counter that has none', function (): void {
            // A counter left behind without a TTL (e.g. by a crash between the old
            // non-atomic INCR and EXPIRE) must not limit its client forever.
            $this->mockClient->storage['marko:cache:counter'] = '5';

            $this->driver->increment('counter', 60);

            expect($this->mockClient->ttls['marko:cache:counter'])->toBe(60)
                ->and($this->mockClient->lastEvalScript)->toContain("'TTL'");
        });

        it('leaves the counter persistent when incremented with a zero ttl', function (): void {
            $this->driver->increment('counter', 0);

            expect($this->mockClient->ttls)->not->toHaveKey('marko:cache:counter');
        });
    });

    it('stores a value with add when the key is absent (redis driver)', function (): void {
        expect($this->driver->add('key', 'value', 60))->toBeTrue()
            ->and($this->driver->get('key'))->toBe('value')
            ->and($this->mockClient->setArgs)->toBe([['EX', 60, 'NX']]);
    });

    it('returns false from add and keeps the value when the key exists (redis driver)', function (): void {
        $this->driver->set('key', 'original', 60);

        expect($this->driver->add('key', 'new', 60))->toBeFalse()
            ->and($this->driver->get('key'))->toBe('original');
    });

    it('applies the default ttl when add is given a null ttl (redis driver)', function (): void {
        $this->driver->add('key', 'value');

        expect($this->mockClient->ttls['marko:cache:key'])->toBe(3600);
    });

    it('stores the value with no expiry when add is given a ttl of 0 (redis driver)', function (): void {
        $this->driver->add('key', 'value', 0);

        expect($this->mockClient->setArgs)->toBe([['NX']])
            ->and($this->mockClient->ttls)->not->toHaveKey('marko:cache:key')
            ->and($this->driver->get('key'))->toBe('value');
    });

    it('writes a signed envelope bound to the prefixed key from add (redis driver)', function (): void {
        $this->driver->add('key', ['a' => 1], 60);

        expect(createSigner()->verifyAndUnwrap($this->mockClient->storage['marko:cache:key'], 'marko:cache:key'))
            ->toBe(serialize(['a' => 1]));
    });

    it('validates the key on add (redis driver)', function (): void {
        expect(fn (): bool => $this->driver->add('', 'value'))->toThrow(InvalidKeyException::class);
    });

    it('delegates remember, pull and forever to CacheOperations (redis driver)', function (): void {
        $operations = new class () extends CacheOperations
        {
            /** @var list<list<mixed>> */
            public array $calls = [];

            public function remember(
                CacheInterface $cache,
                string $key,
                ?int $ttl,
                callable $callback,
            ): mixed {
                $this->calls[] = ['remember', $cache, $key, $ttl];

                return null;
            }

            public function pull(
                CacheInterface $cache,
                string $key,
                mixed $default,
            ): mixed {
                $this->calls[] = ['pull', $cache, $key, $default];

                return null;
            }

            public function forever(
                CacheInterface $cache,
                string $key,
                mixed $value,
            ): bool {
                $this->calls[] = ['forever', $cache, $key, $value];

                return true;
            }
        };
        $driver = createDriver($this->mockClient, operations: $operations);

        $driver->remember('a', 60, fn (): string => 'computed');
        $driver->pull('a', 'default');
        $driver->forever('b', 'value');

        expect($operations->calls)->toBe([
            ['remember', $driver, 'a', 60],
            ['pull', $driver, 'a', 'default'],
            ['forever', $driver, 'b', 'value'],
        ]);
    });
});
