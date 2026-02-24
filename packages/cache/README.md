# Marko Cache

Interfaces for caching--defines how data is stored, retrieved, and expired, not how the backend works.

## Overview

Cache provides the contracts and shared infrastructure for Marko's caching system. Type-hint against `CacheInterface` in your modules and let the installed driver handle the backend. Includes CLI commands for cache management, a `CacheItem` value object with metadata, and key validation.

**This package defines contracts only.** Install a driver for implementation:

- `marko/cache-array` -- In-memory (development/testing)
- `marko/cache-file` -- File-based (default)
- `marko/cache-redis` -- Redis (production)

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
$item = $this->cache->getItem('product.42');

if ($item->isHit()) {
    $value = $item->get();
    $expiresAt = $item->expiresAt();
}
```

### Batch Operations

Store and retrieve multiple values at once:

```php
$this->cache->setMultiple([
    'user.1' => $user1,
    'user.2' => $user2,
], ttl: 600);

$users = $this->cache->getMultiple(['user.1', 'user.2']);
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko cache:clear` | Clear all cached items |
| `marko cache:status` | Show cache driver and statistics |

## API Reference

### CacheInterface

```php
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

### CacheItemInterface

```php
public function getKey(): string;
public function get(): mixed;
public function isHit(): bool;
public function expiresAt(): ?DateTimeInterface;
```
