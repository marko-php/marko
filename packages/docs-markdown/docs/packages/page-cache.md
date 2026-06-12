---
title: marko/page-cache
description: Contracts, middleware, and CLI for full-page HTTP response caching — cache entire responses to serve pages in microseconds.
---

Contracts, middleware, and CLI for full-page HTTP response caching --- cache entire responses to serve pages in microseconds. This is an interface package that defines the contracts, attributes, and middleware for full-page HTTP response caching. It ships no storage backend --- pair it with a driver such as `marko/page-cache-file`. Caching is opt-in: only controller actions annotated with `#[Cacheable]` are eligible. `PageCacheMiddleware` is automatically registered as the first global middleware, so no manual wiring is needed.

**This package defines contracts only.** Install a driver for implementation:

- `marko/page-cache-file` --- File-based (default)

For automatic cache invalidation when entities change, install [marko/page-cache-entity](/docs/packages/page-cache-entity/).

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
use Marko\PageCache\CacheabilityChecker;
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

### Dynamic Tags from the Request

Use the `provider` parameter on `#[Cacheable]` to append tags at runtime based on the current request:

```php
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products/{id}')]
    #[Cacheable(ttl: 3600, tags: ['products'], provider: ProductTagProvider::class)]
    public function show(int $id): Response
    {
        return Response::ok($this->productRepository->find($id));
    }
}

final class ProductTagProvider implements CacheTagProviderInterface
{
    public function tags(Request $request, Cacheable $attribute): array
    {
        $id = $request->routeParam('id');

        return ["product-{$id}"];
    }
}
```

Provider tags are appended to the static `tags` array and deduplicated. The provider class is resolved via the DI container.

### Entity-Driven Invalidation

Entities can declare which cache tags they own by implementing `IdentityInterface`:

```php
use Marko\PageCache\Contracts\IdentityInterface;

class Product implements IdentityInterface
{
    public function getIdentities(): array
    {
        return ['products', "product-{$this->id}"];
    }
}
```

`IdentityInterface` lives in `marko/page-cache` so that domain entities depend only on the cache contract. The actual auto-purge behaviour --- observing save/delete events and calling `purgeTag()` --- requires installing [marko/page-cache-entity](/docs/packages/page-cache-entity/).

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
use Marko\PageCache\CachePolicy;
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
    public function __construct(
        public int $ttl,
        public array $tags = [],
        public ?string $provider = null,
    ) {}
}
```

The optional `provider` parameter accepts a class name implementing `CacheTagProviderInterface`. When set, the provider is resolved via the DI container at request time and its returned tags are appended to the static `tags` array (deduplicated).

### CacheTagProviderInterface

Implement this interface to compute cache tags dynamically from the current request. Resolved via the DI container.

```php
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Http\Request;

public function tags(Request $request, Cacheable $attribute): array;
```

### IdentityInterface

Implement this interface on domain entities to declare which cache tags they own. Tags returned here are purged when the entity is created, updated, or deleted (requires [marko/page-cache-entity](/docs/packages/page-cache-entity/)).

```php
use Marko\PageCache\Contracts\IdentityInterface;

public function getIdentities(): array;
```

### CacheKey

```php
use Marko\PageCache\CacheKey;
use Marko\Routing\Http\Request;

public static function fromRequest(Request $request): self;
public static function normalizeQuery(string $rawQuery): string;
public function hash(): string;
```

`normalizeQuery()` is used by both store and purge operations. It parses the raw query string with `parse_str`, sorts keys, and re-encodes with RFC 3986 percent-encoding (`http_build_query(..., PHP_QUERY_RFC3986)`). This ensures that a URL stored with a space (`q=hello%20world`) and a URL purged with a `+` (`q=hello+world`) hash to the same cache key, so purge-by-URL never silently misses.

### CachePolicy

```php
use Marko\PageCache\CachePolicy;

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

| Exception / Factory | Description |
|---|---|
| `PageCacheException` | Base exception for all page-cache errors |
| `NoDriverException` | Thrown when no driver is bound to `PageCacheInterface` |
| `PageCacheException::invalidTagProvider()` | Thrown when the class named in `provider` does not implement `CacheTagProviderInterface` |
| `PageCacheException::missingEntityBridge()` | Thrown at boot when a class implements `IdentityInterface` but `marko/page-cache-entity` is not installed |

## Related Packages

- [marko/page-cache-file](/docs/packages/page-cache-file/) --- File-based driver implementation
- [marko/page-cache-entity](/docs/packages/page-cache-entity/) --- Auto-purge page-cache tags when entities change
