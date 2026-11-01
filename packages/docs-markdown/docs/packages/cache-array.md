---
title: marko/cache-array
description: In-memory cache driver — stores data for the duration of a single request with zero I/O overhead.
---

In-memory cache driver --- stores data for the duration of a single request with zero I/O overhead. The array cache driver keeps all cached data in a PHP array. Data does not persist across requests, making it ideal for development, testing, and single-request deduplication (e.g., avoiding duplicate database queries within one request).

Implements `CacheInterface` from [marko/cache](/docs/packages/cache/).

## Installation

```bash
composer require marko/cache-array
```

This automatically installs `marko/cache`.

## Configuration

Set the cache driver to `array` in your config:

```php title="config/cache.php"
return [
    'driver' => 'array',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

## Usage

Once configured, inject `CacheInterface` as usual --- the array driver is used automatically:

```php
use Marko\Cache\Contracts\CacheInterface;

class ExpensiveService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function compute(
        string $key,
    ): array {
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $result = $this->doExpensiveWork($key);
        $this->cache->set($key, $result);

        return $result;
    }
}
```

### When to Use

- **Development** --- Fast iteration without external dependencies
- **Testing** --- Predictable, isolated cache behavior per test
- **Request deduplication** --- Cache expensive computations within a single request

For persistent caching, use `marko/cache-file` or `marko/cache-redis`.

### Atomic Locks

The package also binds `LockStoreInterface` to `Marko\Cache\Memory\Lock\ArrayLockStore` as a singleton, so [atomic locks](/docs/packages/cache/#atomic-locks) work through `LockFactoryInterface`. Its scope is `LockScope::Process`: locks live in memory and exclude callers **in the same PHP process only**. Use it in tests, and use `marko/cache-redis` for real cross-worker exclusion. Construct it with a `FakeClock` to expire locks without sleeping:

```php
use Marko\Cache\Memory\Lock\ArrayLockStore;
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-01-01 12:00:00 UTC');
$store = new ArrayLockStore($clock);

$store->acquire('video-pipeline:42', 'owner-a', 30);
$clock->travel('+30 seconds');

$store->currentOwner('video-pipeline:42'); // null: the lock expired
```

### Time and Expiry

The driver reads the current time through the PSR-20 `ClockInterface` from [`marko/clock`](/docs/packages/clock/), which the container injects. In tests, construct it with a [`FakeClock`](/docs/packages/testing/#fakeclock) and call `travel()` to cross a TTL boundary exactly, without `sleep()`:

```php
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

$clock = new FakeClock('2026-01-01 12:00:00 UTC');
$cache = new ArrayCacheDriver(
    new CacheConfig(new FakeConfigRepository(['cache.default_ttl' => 3600]), new ProjectPaths()),
    $clock,
    new CacheOperations(),
);

$cache->set('report', $report, ttl: 300);
$clock->travel('+301 seconds');

$cache->has('report'); // false
```

## API Reference

Implements all methods from `CacheInterface`. See [marko/cache](/docs/packages/cache/) for the full contract.

| Method | Description |
|---|---|
| `get(string $key, mixed $default = null): mixed` | Retrieve a value, or `$default` if missing/expired |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store a value with optional TTL (defaults to `default_ttl` from config) |
| `has(string $key): bool` | Check if a non-expired entry exists |
| `delete(string $key): bool` | Remove an entry |
| `clear(): bool` | Remove all entries |
| `getItem(string $key): CacheItemInterface` | Retrieve a `CacheItem` with hit/miss metadata |
| `getMultiple(array $keys, mixed $default = null): iterable` | Retrieve multiple values at once |
| `setMultiple(array $values, ?int $ttl = null): bool` | Store multiple values at once |
| `deleteMultiple(array $keys): bool` | Remove multiple entries at once |
| `increment(string $key, int $ttl): int` | Atomically increment an integer counter; TTL applied only on first increment |
| `add(string $key, mixed $value, ?int $ttl = null): bool` | Store a value only if the key is absent or expired; `false` when an unexpired value exists |
| `remember(string $key, ?int $ttl, callable $callback): mixed` | Return the cached value, or compute and store it on a miss (delegates to [`CacheOperations`](/docs/packages/cache/#cacheoperations)) |
| `pull(string $key, mixed $default = null): mixed` | Get a value and delete it (delegates to `CacheOperations`) |
| `forever(string $key, mixed $value): bool` | Store a value with no expiry (delegates to `CacheOperations`) |

`add()` checks and writes the in-process store with nothing in between, so it is atomic within the process. The store itself is per-process, so use `marko/cache-file` or `marko/cache-redis` when `add()` must be atomic across requests.

TTL behavior: a positive TTL sets an expiration timestamp relative to the injected clock. A `null` TTL falls back to the configured `default_ttl`. A TTL of `0` or less means the entry never expires. Expired entries are lazily purged on the next read.
