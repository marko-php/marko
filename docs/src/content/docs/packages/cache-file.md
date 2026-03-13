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

This automatically installs `marko/cache`.

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

### Storage Details

- Each cache key is hashed with `xxh128` and stored as a `.cache` file in the configured path.
- Writes use a temp file with `LOCK_EX` followed by an atomic `rename()` to prevent corruption.
- A TTL of `0` or `null` means the entry never expires.
- Expired entries are deleted lazily --- on the next `get()`, `has()`, or `getItem()` call for that key.
