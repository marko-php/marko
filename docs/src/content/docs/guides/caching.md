---
title: Caching
description: Cache data with pluggable backends — file, Redis, or array for testing.
---

Marko's cache system follows the interface/implementation pattern. Code against `CacheInterface`, swap backends by changing a binding.

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

use Marko\Cache\Contracts\CacheInterface;

class PostService
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function getPopularPosts(): array
    {
        $posts = $this->cache->get('posts.popular');

        if ($posts === null) {
            $posts = $this->fetchPopularPostsFromDb();
            $this->cache->set('posts.popular', $posts, ttl: 3600);
        }

        return $posts;
    }

    public function clearPostCache(): void
    {
        $this->cache->delete('posts.popular');
    }
}
```

## Cache Operations

```php
use Marko\Cache\Contracts\CacheInterface;

// Store a value (TTL in seconds)
$this->cache->set('key', $value, ttl: 3600);

// Retrieve a value (with optional default)
$value = $this->cache->get('key', default: 'fallback');

// Check if a key exists
$this->cache->has('key'); // bool

// Remove a key
$this->cache->delete('key');

// Store multiple values
$this->cache->setMultiple(['key1' => $val1, 'key2' => $val2], ttl: 3600);

// Retrieve multiple values
$values = $this->cache->getMultiple(['key1', 'key2']);

// Clear all cache
$this->cache->clear();
```

## Switching Backends

Change from file cache to Redis by updating your `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => RedisCacheDriver::class,
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
