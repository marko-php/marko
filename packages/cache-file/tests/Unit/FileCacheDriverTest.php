<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\CacheException;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Cache\File\Exceptions\FileCacheException;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function getCacheTestPath(): string
{
    return sys_get_temp_dir() . '/marko-cache-test-' . bin2hex(random_bytes(8));
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

function createTestCacheSigner(
    string $key = 'file-cache-test-signing-key',
): CacheValueSigner {
    return new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
        'encryption.key' => $key,
    ])));
}

function createTestCacheConfig(
    string $path,
    int $defaultTtl = 3600,
): CacheConfig {
    return new CacheConfig(new FakeConfigRepository([
        'cache.path' => $path,
        'cache.default_ttl' => $defaultTtl,
        'cache.driver' => 'file',
    ]));
}

/**
 * Write a cache entry that expired 10 seconds before the given time.
 */
function writeExpiredCacheEntry(
    string $cachePath,
    string $key,
    mixed $value,
    int $now,
): void {
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
    }

    $hash = hash('xxh128', $key);
    $filePath = $cachePath . '/' . $hash . '.cache';

    $data = [
        'value' => $value,
        'expires_at' => $now - 10,
        'created_at' => $now - 20,
    ];

    file_put_contents($filePath, createTestCacheSigner()->wrap(serialize($data), $key));
}

beforeEach(function (): void {
    $this->cachePath = getCacheTestPath();
    $this->config = createTestCacheConfig($this->cachePath);
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->signer = createTestCacheSigner();
    $this->driver = new FileCacheDriver($this->config, $this->clock, $this->signer, new CacheOperations());
});

afterEach(function (): void {
    cleanupCacheTestPath($this->cachePath);
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

    expect($this->driver->get('key'))->toEqual($object);
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

it('expires items after ttl', function (): void {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value', $this->clock->now()->getTimestamp());

    expect($this->driver->get('key'))->toBeNull();
});

it('does not expire items with zero ttl', function (): void {
    $this->driver->set('key', 'value', 0);

    expect($this->driver->get('key'))->toBe('value');
});

it('uses default ttl when not specified', function (): void {
    $cachePath = getCacheTestPath();
    $config = createTestCacheConfig($cachePath, 30);
    $driver = new FileCacheDriver($config, $this->clock, createTestCacheSigner(), new CacheOperations());
    $driver->set('key', 'value');

    $this->clock->travel('+30 seconds');
    expect($driver->get('key'))->toBe('value');

    $this->clock->travel('+1 second');
    expect($driver->get('key'))->toBeNull();

    cleanupCacheTestPath($cachePath);
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

it('creates cache directory if not exists', function (): void {
    $newPath = sys_get_temp_dir() . '/marko-cache-new-' . bin2hex(random_bytes(8));
    $config = createTestCacheConfig($newPath);
    $driver = new FileCacheDriver($config, $this->clock, createTestCacheSigner(), new CacheOperations());

    $driver->set('key', 'value');

    expect(is_dir($newPath))->toBeTrue();

    cleanupCacheTestPath($newPath);
});

it('handles concurrent access safely', function (): void {
    $this->driver->set('key', 'initial');

    $result = $this->driver->set('key', 'updated');

    expect($result)->toBeTrue()
        ->and($this->driver->get('key'))->toBe('updated');
});

it('removes expired item on has check', function (): void {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value', $this->clock->now()->getTimestamp());

    expect($this->driver->has('key'))->toBeFalse();
});

it('removes expired item on getItem', function (): void {
    writeExpiredCacheEntry($this->cachePath, 'key', 'value', $this->clock->now()->getTimestamp());

    $item = $this->driver->getItem('key');

    expect($item->isHit())->toBeFalse();
});

it('returns 1 when incrementing a key that does not yet exist (file driver)', function (): void {
    expect($this->driver->increment('counter', 60))->toBe(1);
});

it('returns the incremented value on a subsequent increment (file driver)', function (): void {
    $this->driver->increment('counter', 60);

    expect($this->driver->increment('counter', 60))->toBe(2);
});

it('returns an int from get() after increment() (file driver)', function (): void {
    $this->driver->increment('counter', 60);
    $this->driver->increment('counter', 60);

    expect($this->driver->get('counter'))->toBe(2)
        ->and($this->driver->getItem('counter')->get())->toBe(2);
});

it('applies the ttl on the first increment so the counter expires (file driver)', function (): void {
    $this->driver->increment('counter', 60);

    $item = $this->driver->getItem('counter');

    expect($item->isHit())->toBeTrue()
        ->and($item->expiresAt())->not->toBeNull();
});

it('does not reset the ttl on a subsequent increment (file driver)', function (): void {
    $this->driver->increment('counter', 60);

    $firstExpiry = $this->driver->getItem('counter')->expiresAt();

    $this->driver->increment('counter', 60);

    $secondExpiry = $this->driver->getItem('counter')->expiresAt();

    expect($secondExpiry)->toEqual($firstExpiry);
});

it(
    'still round-trips a legitimately stored object value through the file cache (object support preserved)',
    function (): void {
        $object = new stdClass();
        $object->name = 'preserved-object';
        $this->driver->set('object-key', $object);

        $result = $this->driver->get('object-key');

        expect($result)->toBeInstanceOf(stdClass::class)
            ->and($result->name)->toBe('preserved-object');
    },
);

it('still round-trips a legitimately stored array value through the file cache', function (): void {
    $value = ['name' => 'cached-array', 'items' => [1, 2, 3]];
    $this->driver->set('array-key', $value);

    expect($this->driver->get('array-key'))->toBe($value);
});

it('treats a file cache entry that decodes to an unexpected shape as a miss', function (): void {
    $hash = hash('xxh128', 'key');
    $filePath = $this->cachePath . '/' . $hash . '.cache';

    if (!is_dir($this->cachePath)) {
        mkdir($this->cachePath, 0755, true);
    }

    // Write a serialized payload that is valid PHP but has the wrong shape (no 'value' key)
    file_put_contents($filePath, $this->signer->wrap(serialize(['corrupt' => 'data']), 'key'));

    expect($this->driver->get('key'))->toBeNull()
        ->and($this->driver->has('key'))->toBeFalse();
});

it(
    'throws FileCacheException with the rename reason and leaves no orphan tmp file when the rename step fails',
    function (): void {
        mkdir($this->cachePath, 0755, true);

        $key = 'orphan-test-key';
        $hash = hash('xxh128', $key);
        $targetPath = $this->cachePath . '/' . $hash . '.cache';

        // Make the target path a directory so rename() fails
        mkdir($targetPath, 0755, true);

        try {
            $this->driver->set($key, 'some-value');
            $this->fail('Expected FileCacheException');
        } catch (FileCacheException $e) {
            expect($e->getMessage())->toBe("Cache entry could not be written: $targetPath")
                ->and($e->getContext())->toContain('rename(');
        } finally {
            // Cleanup the directory we created as the "target"
            rmdir($targetPath);
        }

        expect(glob($this->cachePath . '/*.tmp.*'))->toBeEmpty();
    },
);

it('throws a CacheException instead of failing open when increment cannot open the counter file', function (): void {
    mkdir($this->cachePath, 0755, true);
    $targetPath = cacheEntryPath($this->cachePath, 'counter');

    // A directory where the counter file should be makes fopen() fail
    mkdir($targetPath, 0755, true);

    try {
        $this->driver->increment('counter', 60);
        $this->fail('Expected FileCacheException');
    } catch (FileCacheException $e) {
        expect($e)->toBeInstanceOf(CacheException::class)
            ->and($e->getMessage())->toBe("Cache entry could not be opened: $targetPath")
            ->and($e->getContext())->toContain('fopen(');
    } finally {
        rmdir($targetPath);
    }
});

it('removes leftover tmp files when clear is called', function (): void {
    mkdir($this->cachePath, 0755, true);

    // Pre-seed a leftover tmp file
    $tmpFile = $this->cachePath . '/somehash.cache.tmp.' . uniqid();
    file_put_contents($tmpFile, 'leftover');

    $this->driver->clear();

    expect(glob($this->cachePath . '/*.tmp.*'))->toBeEmpty();
});

it('still removes cache files when clear is called', function (): void {
    $this->driver->set('key1', 'value1');
    $this->driver->set('key2', 'value2');

    $this->driver->clear();

    expect(glob($this->cachePath . '/*.cache'))->toBeEmpty();
});

it('does not error when the cache directory already exists', function (): void {
    // Pre-create the directory (simulates a concurrent creator winning the race)
    mkdir($this->cachePath, 0755, true);

    // set() and setMultiple() call ensureDirectoryExists() — must not throw, and
    // must not call mkdir() (which raises "File exists", even when suppressed)
    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $result = $this->driver->set('key', 'value');
        $this->driver->set('key', 'again');
        $this->driver->setMultiple(['a' => 1, 'b' => 2]);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeTrue()
        ->and($warnings)->toBeEmpty();
});

it('throws FileCacheException with the OS reason when the cache directory cannot be created', function (): void {
    // A regular file where a parent directory should be makes mkdir() fail
    mkdir($this->cachePath, 0755, true);
    $blocker = $this->cachePath . '/not-a-directory';
    file_put_contents($blocker, 'x');

    $driver = new FileCacheDriver(
        createTestCacheConfig($blocker . '/cache'),
        $this->clock,
        $this->signer,
        new CacheOperations(),
    );

    try {
        $driver->set('key', 'value');
        $this->fail('Expected FileCacheException');
    } catch (FileCacheException $e) {
        expect($e)->toBeInstanceOf(CacheException::class)
            ->and($e->getMessage())->toBe("Cache directory could not be created: $blocker/cache")
            ->and($e->getContext())->toContain('mkdir(): Not a directory');
    }
});

it('creates the cache directory when it is missing', function (): void {
    // $this->cachePath does not exist yet
    expect(is_dir($this->cachePath))->toBeFalse();

    $result = $this->driver->set('key', 'value');

    expect($result)->toBeTrue()
        ->and(is_dir($this->cachePath))->toBeTrue();
});

it('writes and reads back a value successfully after directory creation', function (): void {
    // Directory does not exist — driver must create it and still write+read correctly
    expect(is_dir($this->cachePath))->toBeFalse();

    $this->driver->set('greeting', 'hello');

    expect($this->driver->get('greeting'))->toBe('hello');
});

/**
 * @return array{value: mixed, expires_at: ?int, created_at: int}
 */
function readCacheFileEntry(
    string $cachePath,
    string $key,
): array {
    $envelope = file_get_contents($cachePath . '/' . hash('xxh128', $key) . '.cache');

    return unserialize(createTestCacheSigner()->verifyAndUnwrap($envelope, $key));
}

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

it('records expires_at and created_at from the clock', function (): void {
    $this->driver->set('key', 'value', 60);

    $entry = readCacheFileEntry($this->cachePath, 'key');
    $now = $this->clock->now()->getTimestamp();

    expect($entry['created_at'])->toBe($now)
        ->and($entry['expires_at'])->toBe($now + 60);
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
        ->and(readCacheFileEntry($this->cachePath, 'counter')['expires_at'])
        ->toBe($this->clock->now()->getTimestamp() + 60);
});

it('keeps counting within the window without moving the expiry', function (): void {
    $this->driver->increment('counter', 60);
    $expiresAt = readCacheFileEntry($this->cachePath, 'counter')['expires_at'];

    $this->clock->travel('+60 seconds');

    expect($this->driver->increment('counter', 60))->toBe(2)
        ->and(readCacheFileEntry($this->cachePath, 'counter')['expires_at'])->toBe($expiresAt);
});

function cacheEntryPath(
    string $cachePath,
    string $key,
): string {
    return $cachePath . '/' . hash('xxh128', $key) . '.cache';
}

/**
 * Replace the payload of a signed cache file while keeping its original HMAC.
 */
function tamperWithCacheEntry(
    string $filePath,
    mixed $value,
): void {
    $envelope = file_get_contents($filePath);
    $planted = serialize(['value' => $value, 'expires_at' => null, 'created_at' => 0]);

    file_put_contents($filePath, substr($envelope, 0, 65) . $planted);
}

it('signs every cache entry it writes with an HMAC envelope', function (): void {
    $this->driver->set('key', 'value');

    $contents = file_get_contents(cacheEntryPath($this->cachePath, 'key'));

    expect($contents)->toMatch('/\A[0-9a-f]{64}\./')
        ->and($this->signer->unwrap($contents, 'key'))->toBe(serialize([
            'value' => 'value',
            'expires_at' => $this->clock->now()->getTimestamp() + 3600,
            'created_at' => $this->clock->now()->getTimestamp(),
        ]));
});

it('round-trips a signed value through set and get', function (): void {
    $this->driver->set('key', ['nested' => 'value']);

    expect($this->driver->get('key'))->toBe(['nested' => 'value'])
        ->and($this->driver->getItem('key')->isHit())->toBeTrue();
});

it('treats a tampered cache file as a miss and deletes it', function (): void {
    $this->driver->set('key', 'value');
    $filePath = cacheEntryPath($this->cachePath, 'key');

    tamperWithCacheEntry($filePath, new stdClass());

    expect($this->driver->get('key', 'default'))->toBe('default')
        ->and(file_exists($filePath))->toBeFalse();
});

it('treats an unsigned legacy cache file as a miss and deletes it', function (): void {
    mkdir($this->cachePath, 0755, true);
    $filePath = cacheEntryPath($this->cachePath, 'key');

    file_put_contents($filePath, serialize(['value' => new stdClass(), 'expires_at' => null, 'created_at' => 0]));

    expect($this->driver->getItem('key')->isHit())->toBeFalse()
        ->and(file_exists($filePath))->toBeFalse();
});

it('reports a tampered cache file as missing from has()', function (): void {
    $this->driver->set('key', 'value');
    tamperWithCacheEntry(cacheEntryPath($this->cachePath, 'key'), 'planted');

    expect($this->driver->has('key'))->toBeFalse();
});

it('treats a signed cache file copied to another cache key as a miss', function (): void {
    $this->driver->set('role-alice', 'admin');
    $this->driver->set('role-bob', 'guest');
    $bobPath = cacheEntryPath($this->cachePath, 'role-bob');

    copy(cacheEntryPath($this->cachePath, 'role-alice'), $bobPath);

    expect($this->driver->get('role-bob'))->toBeNull()
        ->and(file_exists($bobPath))->toBeFalse()
        ->and($this->driver->get('role-alice'))->toBe('admin');
});

it('treats a cache file signed with a different key as a miss', function (): void {
    $otherDriver = new FileCacheDriver(
        $this->config,
        $this->clock,
        createTestCacheSigner('some-other-key'),
        new CacheOperations(),
    );
    $otherDriver->set('key', 'value');

    expect($this->driver->get('key'))->toBeNull();
});

it('resets the counter when incrementing a tampered cache file', function (): void {
    $this->driver->increment('counter', 60);
    $this->driver->increment('counter', 60);
    $filePath = cacheEntryPath($this->cachePath, 'counter');

    tamperWithCacheEntry($filePath, 1000);

    expect($this->driver->increment('counter', 60))->toBe(1)
        ->and($this->signer->unwrap(file_get_contents($filePath), 'counter'))->not->toBeNull()
        ->and($this->driver->get('counter'))->toBe(1);
});

it('resets the counter when incrementing an unsigned legacy cache file', function (): void {
    mkdir($this->cachePath, 0755, true);
    file_put_contents(
        cacheEntryPath($this->cachePath, 'counter'),
        serialize(['value' => 41, 'expires_at' => null, 'created_at' => 0]),
    );

    expect($this->driver->increment('counter', 60))->toBe(1);
});

it('throws TamperedCacheValueException on write when no signing key is configured', function (): void {
    $driver = new FileCacheDriver($this->config, $this->clock, createTestCacheSigner(''), new CacheOperations());

    expect(fn () => $driver->set('key', 'value'))
        ->toThrow(TamperedCacheValueException::class, 'the encryption key is empty')
        ->and(fn () => $driver->increment('counter', 60))
        ->toThrow(TamperedCacheValueException::class, 'the encryption key is empty');
});

it('throws TamperedCacheValueException on read when no signing key is configured', function (): void {
    $this->driver->set('key', 'value');
    $driver = new FileCacheDriver($this->config, $this->clock, createTestCacheSigner(''), new CacheOperations());

    expect(fn () => $driver->get('key'))
        ->toThrow(TamperedCacheValueException::class, 'the encryption key is empty')
        ->and(file_exists(cacheEntryPath($this->cachePath, 'key')))->toBeTrue();
});

it('stores a value with add when the key is absent (file driver)', function (): void {
    expect($this->driver->add('key', 'value', 60))->toBeTrue()
        ->and($this->driver->get('key'))->toBe('value');
});

it('returns false from add and keeps the value when the key exists and is unexpired (file driver)', function (): void {
    $this->driver->set('key', 'original', 60);

    expect($this->driver->add('key', 'new', 60))->toBeFalse()
        ->and($this->driver->get('key'))->toBe('original');
});

it('returns true from add once the existing key has expired (file driver)', function (): void {
    $this->driver->set('key', 'original', 60);
    $this->clock->travel('+61 seconds');

    expect($this->driver->add('key', 'new', 60))->toBeTrue()
        ->and($this->driver->get('key'))->toBe('new');
});

it('returns true from add over a tampered entry without unserializing it (file driver)', function (): void {
    mkdir($this->cachePath, 0755, true);
    file_put_contents($this->cachePath . '/' . hash('xxh128', 'key') . '.cache', serialize('planted'));

    expect($this->driver->add('key', 'value', 60))->toBeTrue()
        ->and($this->driver->get('key'))->toBe('value');
});

it('applies the default ttl when add is given a null ttl (file driver)', function (): void {
    $this->driver->add('key', 'value');

    expect($this->driver->getItem('key')->expiresAt()->getTimestamp())
        ->toBe($this->clock->now()->getTimestamp() + 3600);
});

it('stores the value with no expiry when add is given a ttl of 0 (file driver)', function (): void {
    $this->driver->add('key', 'value', 0);
    $this->clock->travel('+10 years');

    expect($this->driver->getItem('key')->expiresAt())->toBeNull()
        ->and($this->driver->get('key'))->toBe('value');
});

it('validates the key on add (file driver)', function (): void {
    expect(fn (): bool => $this->driver->add('invalid/key', 'value'))->toThrow(InvalidKeyException::class);
});

it('lets exactly one of several concurrent processes add the same key (file driver)', function (): void {
    $autoload = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
    $worker = dirname(__DIR__) . '/Fixtures/add-worker.php';
    $startAt = (string) (microtime(true) + 0.5);
    $processes = [];

    foreach (range(1, 6) as $i) {
        $process = proc_open(
            [PHP_BINARY, $worker, $autoload, $this->cachePath, 'file-cache-test-signing-key', $startAt, "worker-$i"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        expect($process)->toBeResource();
        $processes[$i] = [$process, $pipes];
    }

    $results = [];

    foreach ($processes as $i => [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        expect(proc_close($process))->toBe(0, "worker $i failed: $errors")
            ->and($output)->toBeIn(['0', '1']);

        $results[$i] = $output;
    }

    $winners = array_keys($results, '1', true);

    expect($winners)->toHaveCount(1)
        ->and($this->driver->get('once'))->toBe('worker-' . $winners[0]);
});

it('delegates remember, pull and forever to CacheOperations (file driver)', function (): void {
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
    $driver = new FileCacheDriver($this->config, $this->clock, $this->signer, $operations);

    $driver->remember('a', 60, fn (): string => 'computed');
    $driver->pull('a', 'default');
    $driver->forever('b', 'value');

    expect($operations->calls)->toBe([
        ['remember', $driver, 'a', 60],
        ['pull', $driver, 'a', 'default'],
        ['forever', $driver, 'b', 'value'],
    ]);
});
