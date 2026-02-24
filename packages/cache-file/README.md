# Marko Cache File

File-based cache driver--persists cached data to disk with automatic expiration and atomic writes.

## Overview

The file cache driver stores serialized cache entries as individual files. Each entry is written atomically (via temp file + rename) to prevent corruption. Expired entries are cleaned up on read. No external services required--works anywhere PHP can write to disk.

Implements `CacheInterface` from `marko/cache`.

## Installation

```bash
composer require marko/cache-file
```

This automatically installs `marko/cache`.

## Usage

### Configuration

Set the cache driver to `file` in your config:

```php
// config/cache.php
return [
    'driver' => 'file',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

The `path` directory is created automatically if it does not exist.

### How It Works

Once configured, inject `CacheInterface` as usual--the file driver is used automatically:

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
