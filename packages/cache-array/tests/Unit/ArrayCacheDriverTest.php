<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function createArrayCacheTestConfig(
    int $defaultTtl = 3600,
): CacheConfig {
    return new CacheConfig(
        new FakeConfigRepository([
            'cache.path' => '/tmp/cache',
            'cache.default_ttl' => $defaultTtl,
            'cache.driver' => 'array',
        ]),
        new ProjectPaths(),
    );
}

beforeEach(function (): void {
    $this->config = createArrayCacheTestConfig();
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->driver = new ArrayCacheDriver($this->config, $this->clock, new CacheOperations());
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
    $value = ['name' => 'test', 'data' => [1, 2, 3]];
    $this->driver->set('key', $value);

    expect($this->driver->get('key'))->toBe($value);
});

it('sets and gets object value', function (): void {
    $object = new stdClass();
    $object->name = 'test';
    $this->driver->set('key', $object);

    expect($this->driver->get('key'))->toBe($object);
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

it('clears all items', function (): void {
    $this->driver->set('key1', 'value1');
    $this->driver->set('key2', 'value2');

    $this->driver->clear();

    expect($this->driver->has('key1'))->toBeFalse()
        ->and($this->driver->has('key2'))->toBeFalse();
});

it('returns true when clearing', function (): void {
    $this->driver->set('key', 'value');

    expect($this->driver->clear())->toBeTrue();
});

it('returns true when clearing empty cache', function (): void {
    expect($this->driver->clear())->toBeTrue();
});

it('does not expire items with zero ttl', function (): void {
    $this->driver->set('key', 'value', 0);

    expect($this->driver->get('key'))->toBe('value');
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
    $this->driver->setMultiple([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);

    expect($this->driver->get('key1'))->toBe('value1')
        ->and($this->driver->get('key2'))->toBe('value2');
});

it('returns true when setting multiple', function (): void {
    expect($this->driver->setMultiple(['key1' => 'value1']))->toBeTrue();
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

it('stores same object reference', function (): void {
    $object = new stdClass();
    $object->name = 'test';
    $this->driver->set('key', $object);

    // Array cache stores the actual reference, not a serialized copy
    expect($this->driver->get('key'))->toBe($object);
});

it('is isolated per instance', function (): void {
    $driver1 = new ArrayCacheDriver($this->config, $this->clock, new CacheOperations());
    $driver2 = new ArrayCacheDriver($this->config, $this->clock, new CacheOperations());

    $driver1->set('key', 'value1');

    expect($driver2->has('key'))->toBeFalse();
});

it('returns 1 when incrementing a key that does not yet exist (array driver)', function (): void {
    expect($this->driver->increment('counter', 60))->toBe(1);
});

it('returns the incremented value on a subsequent increment (array driver)', function (): void {
    $this->driver->increment('counter', 60);

    expect($this->driver->increment('counter', 60))->toBe(2);
});

it('returns an int from get() after increment() (array driver)', function (): void {
    $this->driver->increment('counter', 60);
    $this->driver->increment('counter', 60);

    expect($this->driver->get('counter'))->toBe(2)
        ->and($this->driver->getItem('counter')->get())->toBe(2);
});

it('keeps an entry until its ttl has elapsed on the clock', function (): void {
    $this->driver->set('key', 'value', 60);

    $this->clock->travel('+60 seconds');

    expect($this->driver->get('key'))->toBe('value')
        ->and($this->driver->has('key'))->toBeTrue();
});

it('expires an entry one second after its ttl on the clock', function (): void {
    $this->driver->set('key', 'value', 60);

    $this->clock->travel('+61 seconds');

    expect($this->driver->has('key'))->toBeFalse()
        ->and($this->driver->get('key', 'default'))->toBe('default')
        ->and($this->driver->getItem('key')->isHit())->toBeFalse();
});

it('expires an entry stored with the default ttl relative to the clock', function (): void {
    $driver = new ArrayCacheDriver(createArrayCacheTestConfig(defaultTtl: 30), $this->clock, new CacheOperations());
    $driver->set('key', 'value');

    $this->clock->travel('+30 seconds');
    expect($driver->has('key'))->toBeTrue();

    $this->clock->travel('+1 second');
    expect($driver->has('key'))->toBeFalse();
});

it('never expires an entry with zero ttl however far the clock moves', function (): void {
    $this->driver->set('key', 'value', 0);

    $this->clock->travel('+10 years');

    expect($this->driver->get('key'))->toBe('value');
});

it('reports the item expiry relative to the clock', function (): void {
    $this->driver->set('key', 'value', 90);

    $expiresAt = $this->driver->getItem('key')->expiresAt();

    expect($expiresAt)->not->toBeNull()
        ->and($expiresAt->getTimestamp())->toBe($this->clock->now()->getTimestamp() + 90);
});

it('restarts an expired counter relative to the clock on increment', function (): void {
    $this->driver->increment('counter', 60);
    $this->driver->increment('counter', 60);

    $this->clock->travel('+61 seconds');

    expect($this->driver->increment('counter', 60))->toBe(1)
        ->and($this->driver->getItem('counter')->expiresAt()->getTimestamp())
        ->toBe($this->clock->now()->getTimestamp() + 60);
});

it('keeps counting within the window without moving the expiry', function (): void {
    $this->driver->increment('counter', 60);
    $expiresAt = $this->driver->getItem('counter')->expiresAt()->getTimestamp();

    $this->clock->travel('+59 seconds');

    expect($this->driver->increment('counter', 60))->toBe(2)
        ->and($this->driver->getItem('counter')->expiresAt()->getTimestamp())->toBe($expiresAt);
});

it('stores a value with add when the key is absent', function (): void {
    expect($this->driver->add('key', 'value', 60))->toBeTrue()
        ->and($this->driver->get('key'))->toBe('value');
});

it('returns false from add and keeps the value when the key exists and is unexpired', function (): void {
    $this->driver->set('key', 'original', 60);

    expect($this->driver->add('key', 'new', 60))->toBeFalse()
        ->and($this->driver->get('key'))->toBe('original');
});

it('returns true from add once the existing key has expired', function (): void {
    $this->driver->set('key', 'original', 60);
    $this->clock->travel('+61 seconds');

    expect($this->driver->add('key', 'new', 60))->toBeTrue()
        ->and($this->driver->get('key'))->toBe('new');
});

it('applies the default ttl when add is given a null ttl', function (): void {
    $this->driver->add('key', 'value');

    expect($this->driver->getItem('key')->expiresAt()->getTimestamp())
        ->toBe($this->clock->now()->getTimestamp() + 3600);
});

it('stores the value with no expiry when add is given a ttl of 0', function (): void {
    $this->driver->add('key', 'value', 0);
    $this->clock->travel('+10 years');

    expect($this->driver->getItem('key')->expiresAt())->toBeNull()
        ->and($this->driver->get('key'))->toBe('value');
});

it('validates the key on add', function (): void {
    expect(fn (): bool => $this->driver->add('', 'value'))->toThrow(InvalidKeyException::class);
});

it('delegates remember, pull and forever to CacheOperations', function (): void {
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
    $driver = new ArrayCacheDriver($this->config, $this->clock, $operations);

    $driver->remember('a', 60, fn (): string => 'computed');
    $driver->pull('a', 'default');
    $driver->forever('b', 'value');

    expect($operations->calls)->toBe([
        ['remember', $driver, 'a', 60],
        ['pull', $driver, 'a', 'default'],
        ['forever', $driver, 'b', 'value'],
    ]);
});
