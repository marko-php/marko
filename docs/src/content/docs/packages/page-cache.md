---
title: marko/page-cache
description: Contracts, middleware, and CLI for full-page HTTP response caching — cache entire responses to serve pages in microseconds.
---

Contracts, middleware, and CLI for full-page HTTP response caching --- cache entire responses to serve pages in microseconds. This is an interface package that defines the contracts, attributes, and middleware for full-page HTTP response caching. It ships no storage backend --- pair it with a driver such as `marko/page-cache-file`. Caching is opt-in: only controller actions annotated with `#[Cacheable]` are eligible. `PageCacheMiddleware` is automatically registered as the first global middleware, so no manual wiring is needed.

**This package defines contracts only.** Install a driver for implementation:

- `marko/page-cache-file` --- File-based (default)

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

Note: Installing a driver package does not automatically install this package. Require both explicitly.

## Usage

### Caching a Controller Action

Annotate any controller action method with `#[Cacheable]` to make its response eligible for caching:

```php
use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products/{id}')]
    #[Cacheable(ttl: 3600, tags: ['products', 'product-{id}'])]
    public function show(int $id): Response
    {
        // This response will be cached for 1 hour
        return Response::ok($this->productRepository->find($id));
    }
}
```

`PageCacheMiddleware` is automatically registered as global middleware. On the first request the response is served from the controller and stored. Subsequent requests return the stored response without executing the controller.

### Known Limitation

Responses with a `Set-Cookie` header are never cached in v1. This includes responses that set analytics or session cookies --- if your response sets any cookie, it bypasses the cache entirely.

### Extending Cacheability Rules

`CacheabilityChecker` determines whether a given request/response pair is eligible for caching. Override it via a [Preference](/docs/packages/core/) to add custom rules --- for example, skipping cache for authenticated users or based on request headers:

```php
use Marko\Core\Attributes\Preference;
use Marko\PageCache\Service\CacheabilityChecker;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Preference(replaces: CacheabilityChecker::class)]
class AuthAwareCacheabilityChecker extends CacheabilityChecker
{
    public function isRequestCacheable(Request $request): bool
    {
        if ($request->hasHeader('X-Auth-Token')) {
            return false;
        }

        return parent::isRequestCacheable($request);
    }
}
```

## Configuration

Add `config/page-cache.php` to your application:

```php title="config/page-cache.php"
return [
    'driver' => env('PAGE_CACHE_DRIVER', 'file'),
    'path'   => env('PAGE_CACHE_PATH', 'storage/page-cache'),
    'ttl'    => (int) env('PAGE_CACHE_TTL', 3600),
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `driver` | `PAGE_CACHE_DRIVER` | `file` | Driver name |
| `path` | `PAGE_CACHE_PATH` | `storage/page-cache` | Root storage directory |
| `ttl` | `PAGE_CACHE_TTL` | `3600` | Default TTL in seconds |

## CLI Commands

| Command | Description |
|---|---|
| `marko page-cache:clear` | Clear all cached pages |
| `marko page-cache:purge <target> [--tag]` | Purge a URL or all entries for a tag |
| `marko page-cache:status` | Show active driver and storage path |

### Examples

```bash
# Show current driver and storage path
marko page-cache:status

# Clear all cached pages
marko page-cache:clear

# Purge a single URL
marko page-cache:purge https://example.com/products/42

# Purge all entries tagged with a given tag
marko page-cache:purge products --tag
```

## API Reference

### PageCacheInterface

```php
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\ValueObjects\CachePolicy;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

public function lookup(Request $request): ?Response;
public function store(Request $request, Response $response, CachePolicy $policy): Response;
public function purgeUrl(string $url): bool;
public function purgeTag(string $tag): bool;
public function clear(): bool;
```

### `#[Cacheable]` Attribute

```php
use Marko\PageCache\Attributes\Cacheable;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    public function __construct(public int $ttl, public array $tags = []) {}
}
```

### CacheKey

```php
use Marko\PageCache\ValueObjects\CacheKey;
use Marko\Routing\Http\Request;

public static function fromRequest(Request $request): self;
public static function normalizeQuery(string $rawQuery): string;
public function hash(): string;
```

### CachePolicy

```php
use Marko\PageCache\ValueObjects\CachePolicy;

public function __construct(public int $ttl, public array $tags) {}
```

### PageCacheConfig

```php
use Marko\PageCache\Config\PageCacheConfig;

public function driver(): string;
public function path(): string;
public function ttl(): int;
```

### Exceptions

| Exception | Description |
|---|---|
| `PageCacheException` | Base exception for all page-cache errors |
| `NoDriverException` | Thrown when no driver is bound to `PageCacheInterface` |

## Related Packages

- [marko/page-cache-file](/docs/packages/page-cache-file/) --- File-based driver implementation
