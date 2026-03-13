---
title: Caching
description: Cache data with pluggable backends — file, Redis, or array for testing.
---

Marko's cache system follows the interface/implementation pattern. Code against `CachePoolInterface`, swap backends by changing a binding.

## Setup

```bash
# File-based caching (default)
composer require marko/cache marko/cache-file

# Redis caching
composer require marko/cache marko/cache-redis
```

## Basic Usage

```php title="app/blog/Service/PostService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Cache\CachePoolInterface;

class PostService
{
    public function __construct(
        private readonly CachePoolInterface $cachePool,
    ) {}

    public function getPopularPosts(): array
    {
        return $this->cachePool->remember('posts.popular', 3600, function () {
            // This closure only runs on cache miss
            return $this->fetchPopularPostsFromDb();
        });
    }

    public function clearPostCache(): void
    {
        $this->cachePool->forget('posts.popular');
    }
}
```

## Cache Operations

```php
// Store a value (TTL in seconds)
$this->cache->put('key', $value, ttl: 3600);

// Retrieve a value
$value = $this->cache->get('key');

// Check if a key exists
$this->cache->has('key'); // bool

// Remove a key
$this->cache->forget('key');

// Get or compute (cache-aside pattern)
$value = $this->cache->remember('key', 3600, fn () => expensiveComputation());

// Clear all cache
$this->cache->flush();
```

## Switching Backends

Change from file cache to Redis by updating your `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Cache\CachePoolInterface;
use Marko\Cache\Redis\RedisCachePool;

return [
    'bindings' => [
        CachePoolInterface::class => RedisCachePool::class,
    ],
];
```

No application code changes needed.

## Available Backends

| Package | Backend | Best For |
|---|---|---|
| `marko/cache-file` | Local filesystem | Development, single-server |
| `marko/cache-redis` | Redis | Production, multi-server |
| `marko/cache-array` | In-memory array | Testing |

## Next Steps

- [Database](/docs/guides/database/) — cache query results
- [Testing](/docs/guides/testing/) — use array cache in tests
- [Cache package reference](/docs/packages/cache/) — full API details
