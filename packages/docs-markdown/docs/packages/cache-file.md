---
title: marko/cache-file
description: File-based cache driver — persists cached data to disk with automatic expiration and atomic writes.
---

File-based cache driver --- persists cached data to disk with automatic expiration and atomic writes. Stores serialized cache entries as individual files, using atomic writes (temp file + rename) to prevent corruption. Expired entries are cleaned up on read. No external services required --- works anywhere PHP can write to disk.

Implements `CacheInterface` from `marko/cache`.

## Installation

```bash
composer require marko/cache-file
```

This automatically installs `marko/cache` and [`marko/encryption`](/docs/packages/encryption/). Every cache file is HMAC-signed with `encryption.key`, so a non-empty key is required. Without one, reads and writes throw `Marko\Cache\Exceptions\TamperedCacheValueException`.

```php title="config/encryption.php"
use Marko\Config\Env;

return [
    'key' => Env::string('ENCRYPTION_KEY', ''),
];
```

## Configuration

Set the cache driver to `file` in your config:

```php title="config/cache.php"
return [
    'driver' => 'file',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

The `path` directory is created automatically if it does not exist.

## Usage

Once configured, inject `CacheInterface` as usual --- the file driver is used automatically:

```php
use Marko\Cache\Contracts\CacheInterface;

class SettingsService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getAll(): array
    {
        $key = 'settings.all';

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $settings = $this->loadFromDatabase();
        $this->cache->set($key, $settings, ttl: 7200);

        return $settings;
    }
}
```

### When to Use

- **Default choice** for most applications
- No external dependencies (Redis, Memcached, etc.)
- Data persists across requests and restarts
- Suitable for single-server deployments

For multi-server deployments or high-throughput caching, use `marko/cache-redis`.

## API Reference

Implements all methods from `CacheInterface`. See `marko/cache` for the full contract.

### Key Methods

| Method | Description |
|---|---|
| `get(string $key, mixed $default = null): mixed` | Retrieve a value, returning `$default` on miss or expiration |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store a value with optional TTL (falls back to `default_ttl`) |
| `has(string $key): bool` | Check if a non-expired entry exists |
| `delete(string $key): bool` | Remove a single entry |
| `clear(): bool` | Remove all cached entries |
| `getItem(string $key): CacheItemInterface` | Get a `CacheItem` with hit/miss status and expiration metadata |
| `getMultiple(array $keys, mixed $default = null): iterable` | Retrieve multiple values at once |
| `setMultiple(array $values, ?int $ttl = null): bool` | Store multiple key-value pairs at once |
| `deleteMultiple(array $keys): bool` | Remove multiple entries at once |
| `increment(string $key, int $ttl): int` | Atomically increment an integer counter; TTL applied only on first increment |
| `add(string $key, mixed $value, ?int $ttl = null): bool` | Atomically store a value only if the key is absent or expired; `false` when an unexpired value exists |
| `remember(string $key, ?int $ttl, callable $callback): mixed` | Return the cached value, or compute and store it on a miss (delegates to [`CacheOperations`](/docs/packages/cache/#cacheoperations)) |
| `pull(string $key, mixed $default = null): mixed` | Get a value and delete it (delegates to `CacheOperations`) |
| `forever(string $key, mixed $value): bool` | Store a value with no expiry (delegates to `CacheOperations`) |

### Storage Details

- Each cache key is hashed with `xxh128` and stored as a `.cache` file in the configured path.
- Each file holds an HMAC-SHA256 envelope (`{hmac}.{serialized-entry}`) signed by [`CacheValueSigner`](/docs/packages/cache/#cachevaluesigner). The HMAC is checked with `hash_equals()` before `unserialize()` runs, so a file planted in the cache directory can't inject objects.
- The HMAC is bound to the cache key, so a file copied over another key's file does not verify.
- A file that is unsigned, malformed, tampered with, copied from another key, or signed with a different key is treated as a miss and deleted; `increment()` restarts such a counter at `1`. Entries signed by an older release (before signing, or before HKDF subkeys and key binding) don't verify, so upgrading (or rotating `encryption.key`) empties the file cache.
- Writes use a temp file with `LOCK_EX` followed by an atomic `rename()` to prevent corruption.
- `increment()` and `add()` share one read-modify-write path: they hold an exclusive `flock()` on the entry's file from the read to the write, so concurrent processes run one after another and exactly one `add()` for a key succeeds. If the file was deleted or replaced while a process waited for the lock, it reopens the new file and locks again, so a write never lands in an unlinked file.
- The cache directory is created on the first write. Later writes check `is_dir()` and never call `mkdir()` again.
- A `null` TTL falls back to `default_ttl` from config. A TTL of `0` or less means the entry never expires.
- Expired entries are deleted lazily --- on the next `get()`, `has()`, or `getItem()` call for that key.
- Expiry and `created_at` timestamps come from the PSR-20 `ClockInterface` ([`marko/clock`](/docs/packages/clock/)), not `time()`. Construct the driver with a [`FakeClock`](/docs/packages/testing/#fakeclock) to test expiry without sleeping (see [Expiry and the Clock](/docs/packages/cache/#expiry-and-the-clock)).

### Errors

`set()`, `setMultiple()`, `increment()` and `add()` throw `Marko\Cache\File\Exceptions\FileCacheException` (a `CacheException`) when the disk refuses the write. They don't return `false`, and `increment()` never fails open by returning `1`: a rate limiter on a full or unwritable cache directory rejects requests loudly instead of allowing all of them. The exception context includes the operating system's reason, such as `Permission denied`, `No space left on device` or `Not a directory`.

| Method | When thrown |
|---|---|
| `FileCacheException::directoryNotCreatable($path, $reason)` | The configured `cache.path` doesn't exist and can't be created |
| `FileCacheException::writeFailed($path, $reason)` | The temp file can't be written or can't be renamed onto the cache entry (the temp file is removed), or `increment()` / `add()` can't write the new entry |
| `FileCacheException::openFailed($path, $reason)` | `increment()` or `add()` can't open the entry's file |
| `FileCacheException::lockFailed($path, $reason)` | `increment()` or `add()` can't take an exclusive `flock()` on the entry's file |

Every read and write path (`get()`, `has()`, `getItem()`, `getMultiple()`, `set()`, `setMultiple()`, `increment()`, `add()`) throws `Marko\Cache\Exceptions\TamperedCacheValueException::emptySigningKey()` when `encryption.key` is empty. A tampered entry never throws: it is a miss.
