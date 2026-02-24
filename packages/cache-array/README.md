# Marko Cache Array

In-memory cache driver--stores data for the duration of a single request with zero I/O overhead.

## Overview

The array cache driver keeps all cached data in a PHP array. Data does not persist across requests. This makes it ideal for development, testing, and single-request deduplication (e.g., avoiding duplicate database queries within one request).

Implements `CacheInterface` from `marko/cache`.

## Installation

```bash
composer require marko/cache-array
```

This automatically installs `marko/cache`.

## Usage

### Configuration

Set the cache driver to `array` in your config:

```php
// config/cache.php
return [
    'driver' => 'array',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

### How It Works

Once configured, inject `CacheInterface` as usual--the array driver is used automatically:

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

- **Development**: Fast iteration without external dependencies
- **Testing**: Predictable, isolated cache behavior per test
- **Request deduplication**: Cache expensive computations within a single request

For persistent caching, use `marko/cache-file` or `marko/cache-redis`.

## API Reference

Implements all methods from `CacheInterface`. See `marko/cache` for the full contract.
