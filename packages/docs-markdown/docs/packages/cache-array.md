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

TTL behavior: a positive TTL sets an expiration timestamp. A `null` TTL falls back to the configured `default_ttl`. A TTL of `0` or less means the entry never expires. Expired entries are lazily purged on the next read.
