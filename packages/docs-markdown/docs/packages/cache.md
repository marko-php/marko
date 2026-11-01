---
title: marko/cache
description: Interfaces for caching — defines how data is stored, retrieved, and expired, not how the backend works.
---

Interfaces for caching --- defines how data is stored, retrieved, and expired, not how the backend works. Cache provides the contracts and shared infrastructure for Marko's caching system. Type-hint against `CacheInterface` in your modules and let the installed driver handle the backend. Includes CLI commands for cache management, a `CacheItem` value object with metadata, and key validation.

**This package defines contracts only.** Install a driver for implementation:

- `marko/cache-array` --- In-memory (development/testing)
- `marko/cache-file` --- File-based (default)
- `marko/cache-redis` --- Redis (production)

## Installation

```bash
composer require marko/cache
```

Note: You typically install a driver package (like `marko/cache-file`) which requires this automatically.

## Usage

### Type-Hinting the Cache

Inject `CacheInterface` wherever you need caching:

```php
use Marko\Cache\Contracts\CacheInterface;

class ProductService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getProduct(
        int $id,
    ): Product {
        return $this->cache->remember(
            "product.$id",
            3600,
            fn (): Product => $this->repository->find($id),
        );
    }
}
```

### TTLs

Every method that stores a value takes a TTL in seconds. `null` means the configured `default_ttl`, and `0` means the value never expires. `forever($key, $value)` is shorthand for `set($key, $value, 0)`.

### Remember, Add, Pull and Forever

`remember()` returns the cached value, or calls the callback on a miss and stores what it returns. A hit is decided by `getItem()->isHit()`, so a cached `null` counts as a hit and the callback isn't called again:

```php
use Marko\Cache\Contracts\CacheInterface;

$report = $this->cache->remember('report.daily', 300, fn (): array => $this->buildReport());
```

`remember()` is not a lock. Two requests that miss at the same moment both run the callback, and the last write wins. If the callback is expensive enough that this matters, guard it with a lock ([#532](https://github.com/marko-php/marko/issues/532) tracks single-flight locks).

`add()` stores a value only when the key holds no unexpired value, and returns `false` otherwise. The check and the write are one atomic step in every bundled driver, so of any number of concurrent callers exactly one gets `true`. Use it to make an action run once, instead of `has()` followed by `set()`, which races:

```php
use Marko\Cache\Contracts\CacheInterface;

if ($this->cache->add("welcome-mail.$userId", true, ttl: 86400)) {
    $this->mailer->send($welcomeMail);
}
```

`pull()` returns a value and deletes it, or returns the default on a miss. It is **not atomic**: two concurrent `pull()` calls can both read the value. Use `add()` or `increment()` when exactly one caller must win.

```php
use Marko\Cache\Contracts\CacheInterface;

$flash = $this->cache->pull("flash.$sessionId", default: []);
$this->cache->forever('feature.flags', $flags);
```

### Cache Item Metadata

Use `getItem()` when you need expiration info alongside the value:

```php
use Marko\Cache\Contracts\CacheInterface;

$item = $this->cache->getItem('product.42');

if ($item->isHit()) {
    $value = $item->get();
    $expiresAt = $item->expiresAt();
}
```

The `CacheItem` value object also provides static factory methods for driver implementations:

```php
use Marko\Cache\CacheItem;
use DateTimeImmutable;

// Create a cache hit
$item = CacheItem::hit('product.42', $value, new DateTimeImmutable('+1 hour'));

// Create a cache miss
$item = CacheItem::miss('product.42');
```

### Batch Operations

Store and retrieve multiple values at once:

```php
use Marko\Cache\Contracts\CacheInterface;

$this->cache->setMultiple([
    'user.1' => $user1,
    'user.2' => $user2,
], ttl: 600);

$users = $this->cache->getMultiple(['user.1', 'user.2']);
```

### Key Validation

Cache keys are validated automatically. Keys cannot be empty or contain reserved characters: `/ \ : * ? " < > | { }`. Invalid keys throw an `InvalidKeyException` with a helpful message:

```php
use Marko\Cache\Exceptions\InvalidKeyException;

// Check key validity without throwing
$valid = InvalidKeyException::isValidKey('my.cache.key'); // true
$valid = InvalidKeyException::isValidKey('invalid/key');  // false
```

### Configuration

The `CacheConfig` class provides typed access to cache configuration values:

```php
use Marko\Cache\Config\CacheConfig;

class MyService
{
    public function __construct(
        private CacheConfig $cacheConfig,
    ) {}

    public function setup(): void
    {
        $driver = $this->cacheConfig->driver();
        $path = $this->cacheConfig->path();
        $defaultTtl = $this->cacheConfig->defaultTtl();
    }
}
```

### Expiry and the Clock

The bundled drivers ([`marko/cache-array`](/docs/packages/cache-array/), [`marko/cache-file`](/docs/packages/cache-file/) and [`marko/cache-redis`](/docs/packages/cache-redis/)) read the current time through the PSR-20 `ClockInterface` from [`marko/clock`](/docs/packages/clock/) instead of calling `time()`. TTLs, expiry checks and `expiresAt()` all follow that clock. To test expiry without `sleep()`, construct a driver with a [`FakeClock`](/docs/packages/testing/#fakeclock) and move it:

```php
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

it('expires a cached value after its ttl', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $cache = new ArrayCacheDriver(
        new CacheConfig(new FakeConfigRepository(['cache.default_ttl' => 3600])),
        $clock,
        new CacheOperations(),
    );
    $cache->set('product.42', 'cached', ttl: 60);

    $clock->travel('+60 seconds');
    expect($cache->get('product.42'))->toBe('cached');

    $clock->travel('+1 second');
    expect($cache->get('product.42'))->toBeNull();
});
```

Redis enforces TTLs on the server, so with `marko/cache-redis` the clock only anchors the reported `expiresAt()`. Moving a `FakeClock` does not expire Redis keys.

### Atomic Locks

An atomic lock is a mutex that worker processes share: only one owner holds a named lock at a time. A `has()` followed by `set()` is a race. A lock claims the name in one atomic step, so use one when work must never run twice at once, such as advancing one video's pipeline or approving a review that was double-clicked. Inject `LockFactoryInterface`; there is no facade:

```php
use Marko\Cache\Contracts\LockFactoryInterface;

class AdvancePipeline
{
    public function __construct(
        private LockFactoryInterface $locks,
    ) {}

    public function handle(
        int $videoId,
    ): void {
        $lock = $this->locks->create("video-pipeline:$videoId", 120);

        if (!$lock->acquire()) {
            return; // another worker is advancing this video
        }

        try {
            // ... the work ...
        } finally {
            $lock->release();
        }
    }
}
```

- **Every lock expires.** The second argument to `create()` is the lock's lifetime in seconds. It must be at least 1, so a holder that crashes can never deadlock the app. Pick the longest time the guarded work can take.
- **Owner tokens.** `create()` gives each lock a random owner token (`owner()`). `release()` frees the lock only for that owner. It returns `false` when another owner holds the lock or it has expired, so a slow holder can't free a lock someone else claimed after it expired. `forceRelease()` frees it whoever holds it.
- **Waiting.** `block($waitSeconds)` retries every 250 ms until the lock is acquired. When the wait runs out it throws `Marko\Cache\Exceptions\LockTimeoutException`. This is a different exception from `Marko\Database\Exceptions\LockTimeoutException`, which is about database row locks.
- **Releasing from another process.** Store `name()` and `owner()`, for example in a job payload. Then `restore($name, $owner)` rebuilds the lock anywhere so you can `release()` it, such as from a job's failure path. A restored lock carries no expiry, so `acquire()` and `block()` on it throw `LockException`.

```php
use Marko\Cache\Exceptions\LockTimeoutException;

$lock = $this->locks->create('reports:nightly', 600);

try {
    $lock->block(5);
} catch (LockTimeoutException) {
    // still held after 5 seconds
}

// Later, in another process:
$this->locks->restore('reports:nightly', $owner)->release();
```

#### Which Driver Excludes What

Each cache driver binds a `LockStoreInterface`, and its `scope()` reports how far the exclusion reaches:

| Driver | Store | `LockScope` | Excludes |
|--------|-------|-------------|----------|
| `marko/cache-redis` | `RedisLockStore` | `Distributed` | processes on every host that shares the Redis server |
| `marko/cache-file` | `FileLockStore` | `Host` | processes on the same host only |
| `marko/cache-array` | `ArrayLockStore` | `Process` | callers in the same PHP process only (tests) |

**Only `marko/cache-redis` gives cross-host exclusion.** With the file store, workers on two servers each get the lock. Code that needs cross-host exclusion checks `$locks->scope() === LockScope::Distributed` and throws otherwise, so it never quietly runs on every host.

Locks live alongside the cache entries, so `marko cache:clear` also frees every held lock. An app that installs no cache driver can still bind its own `LockStoreInterface` with a [Preference](/docs/concepts/preferences/). Without any store, resolving `LockFactoryInterface` throws `NoDriverException` with a list of the driver packages.

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko cache:clear` | Clear all cached items |
| `marko cache:status` | Show cache driver and statistics |

## API Reference

### CacheInterface

```php
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;

public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value, ?int $ttl = null): bool;
public function has(string $key): bool;
public function delete(string $key): bool;
public function clear(): bool;
public function getItem(string $key): CacheItemInterface;
public function getMultiple(array $keys, mixed $default = null): iterable;
public function setMultiple(array $values, ?int $ttl = null): bool;
public function deleteMultiple(array $keys): bool;
public function increment(string $key, int $ttl): int;
public function add(string $key, mixed $value, ?int $ttl = null): bool;
public function remember(string $key, ?int $ttl, callable $callback): mixed;
public function pull(string $key, mixed $default = null): mixed;
public function forever(string $key, mixed $value): bool;
```

All methods that accept keys throw `InvalidKeyException` for empty or invalid keys. For every `$ttl` that stores a value (`set()`, `setMultiple()`, `add()`, `remember()`), `null` means the default TTL and `0` means no expiry.

`increment()` atomically increments the integer stored at `$key` and returns the new value. If the key does not exist it is created with value `1` and the TTL is applied at that moment. On subsequent increments the TTL is **not** reset --- resetting on every call would turn a fixed rate-limit window into a never-closing window. The counter is a plain integer: on every driver, `get()`, `getItem()` and `getMultiple()` return an incremented key as an `int`.

`add()` is an atomic set-if-absent: it returns `false` when an unexpired value already exists. `remember()` computes and stores a value on a miss and returns a cached `null` without calling the callback; it isn't single-flight. `pull()` is get-then-delete and isn't atomic. `forever()` is `set($key, $value, 0)`. See [Remember, Add, Pull and Forever](#remember-add-pull-and-forever).

### CacheOperations

`Marko\Cache\CacheOperations` holds the one implementation of `remember()`, `pull()` and `forever()`, built on the `CacheInterface` primitives (`getItem()`, `set()`, `delete()`). It is stateless and takes the cache as its first argument. Every bundled driver receives it through its constructor and delegates to it, so `add()` is the only operation a driver implements itself. Register a [Preference](/docs/concepts/preferences/) for `CacheOperations` to change these operations for every driver at once.

```php
use Marko\Cache\CacheOperations;
use Marko\Cache\Contracts\CacheInterface;

public function remember(CacheInterface $cache, string $key, ?int $ttl, callable $callback): mixed;
public function pull(CacheInterface $cache, string $key, mixed $default): mixed;
public function forever(CacheInterface $cache, string $key, mixed $value): bool;
```

A custom driver that implements `CacheInterface` does the same: inject `CacheOperations` and delegate each method in one line, for example `return $this->operations->remember($this, $key, $ttl, $callback);`.

### CacheItemInterface

```php
use Marko\Cache\Contracts\CacheItemInterface;
use DateTimeInterface;

public function getKey(): string;
public function get(): mixed;
public function isHit(): bool;
public function expiresAt(): ?DateTimeInterface;
```

### CacheConfig

```php
use Marko\Cache\Config\CacheConfig;

public function driver(): string;
public function path(): string;
public function defaultTtl(): int;
```

### LockFactoryInterface

```php
use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockInterface;
use Marko\Cache\Lock\LockScope;

public function create(string $name, int $seconds): LockInterface;  // fresh random owner; $seconds >= 1
public function restore(string $name, string $owner): LockInterface; // release-only lock for another process
public function scope(): LockScope;                                  // the bound store's scope
```

`marko/cache` binds it to `Marko\Cache\Lock\LockFactory`, which builds `Marko\Cache\Lock\Lock` instances on the bound store.

### LockInterface

```php
use Marko\Cache\Contracts\LockInterface;

public function acquire(): bool;              // one non-blocking attempt
public function block(int $waitSeconds): void; // retry until acquired; throws LockTimeoutException
public function release(): bool;              // false unless this owner holds it
public function forceRelease(): void;
public function owner(): string;
public function name(): string;
```

`block()` sleeps `Lock::RETRY_INTERVAL_MILLISECONDS` (250) between attempts through `Marko\Cache\Lock\SleeperInterface`, bound to `UsleepSleeper`. Bind your own sleeper in tests so `block()` doesn't really wait.

### LockStoreInterface

The only part a driver implements. Owner tokens, expiry validation, `block()` and `restore()` live once in `Lock` and `LockFactory`.

```php
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;

public function acquire(string $name, string $owner, int $seconds): bool; // atomic set-if-absent with expiry
public function release(string $name, string $owner): bool;               // atomic compare-and-delete
public function forceRelease(string $name): void;
public function currentOwner(string $name): ?string;                      // null when free or expired
public function scope(): LockScope;                                       // Process, Host or Distributed
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `CacheException` | Base exception for all cache errors --- includes `getContext()` and `getSuggestion()` methods |
| `InvalidKeyException` | Thrown when a cache key is empty or contains reserved characters |
| `ItemNotFoundException` | Thrown when a requested cache item does not exist |
| `LockException` | Thrown for an empty lock name or owner, an expiry below 1 second, a negative `block()` wait, or `acquire()` on a restored lock |
| `LockTimeoutException` | Thrown by `LockInterface::block()` when the lock stays held for the whole wait (not the database row-lock exception of the same short name) |
| `NoDriverException` | Thrown when a cache interface, or the `LockStoreInterface` behind `LockFactoryInterface`, has no driver bound |
| `TamperedCacheValueException` | Thrown when `encryption.key` is empty, so cache values can't be signed or verified. The Redis driver also throws it when a stored value's HMAC does not verify |

### CacheValueSigner

`Marko\Cache\Signer\CacheValueSigner` signs serialized cache payloads with HMAC-SHA256. Drivers that persist values outside the PHP process (`marko/cache-file`, `marko/cache-redis`) use it so `unserialize()` only ever sees bytes the application wrote itself. The envelope format is `{64-char-hex-hmac}.{serialized-payload}`.

- **Derived key.** The HMAC key is a subkey derived from `encryption.key` ([`marko/encryption`](/docs/packages/encryption/)) with `hash_hkdf('sha256', $key, 32, 'marko-cache-signer')`. The key that encrypts data is never reused for signing, and the cache subkey differs from the [queue envelope's](/docs/packages/queue/#payload-envelope-format).
- **Bound to the storage key.** Every method takes a `$context` string, and the MAC covers it along with the payload. Drivers pass the key the entry is stored under (the prefixed Redis key, or the cache key for files), so a validly signed value copied to another key does not verify.

```php
use Marko\Cache\Signer\CacheValueSigner;

public function wrap(string $serialized, string $context): string;            // sign a payload for $context
public function unwrap(string $envelope, string $context): ?string;           // null when unsigned, malformed, tampered or signed for another context
public function verifyAndUnwrap(string $envelope, string $context): string;   // throws TamperedCacheValueException instead of returning null
```

Every method throws `TamperedCacheValueException::emptySigningKey()` when `encryption.key` is empty.

Entries signed by a release before HKDF subkeys and key binding no longer verify. The file driver treats them as misses and deletes them. The Redis driver throws `TamperedCacheValueException` for them, so run `marko cache:clear` when you deploy the upgrade.
