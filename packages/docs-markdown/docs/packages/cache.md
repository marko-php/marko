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
        $key = "product.$id";

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $product = $this->repository->find($id);
        $this->cache->set($key, $product, ttl: 3600);

        return $product;
    }
}
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
```

All methods that accept keys throw `InvalidKeyException` for empty or invalid keys.

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

### Exceptions

| Exception | Description |
|-----------|-------------|
| `CacheException` | Base exception for all cache errors --- includes `getContext()` and `getSuggestion()` methods |
| `InvalidKeyException` | Thrown when a cache key is empty or contains reserved characters |
| `ItemNotFoundException` | Thrown when a requested cache item does not exist |
