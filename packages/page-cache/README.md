# marko/page-cache

Cache full HTTP responses to make pages render in microseconds.

## Overview

`marko/page-cache` is an interface package that defines the contracts, attributes, and middleware for full-page HTTP response caching. It ships no storage backend — you must pair it with a driver package such as `marko/page-cache-file`. Caching is opt-in: only controller actions annotated with `#[Cacheable]` are eligible to be cached. The `PageCacheMiddleware` is automatically registered as global middleware, so no manual wiring is needed.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

## Usage

### Caching a controller action

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

The `PageCacheMiddleware` is automatically registered as global middleware. No wiring needed.

### CLI commands

```bash
# Show current driver and storage path
php marko page-cache:status

# Clear all cached pages
php marko page-cache:clear

# Purge a single URL
php marko page-cache:purge https://example.com/products/42

# Purge all entries tagged with a given tag
php marko page-cache:purge products --tag
```

### Known limitation

Responses with a `Set-Cookie` header are never cached in v1. This includes responses that set analytics or session cookies — if your response sets any cookie, it bypasses the cache.

## Customization

`CacheabilityChecker` can be extended via [Preferences](https://marko.build/docs/packages/page-cache/#preferences) to add custom cacheability rules — for example, to skip caching for logged-in users or based on request headers.

## API Reference

### `PageCacheInterface`

```php
public function lookup(Request $request): ?Response;
public function store(Request $request, Response $response, CachePolicy $policy): Response;
public function purgeUrl(string $url): bool;
public function purgeTag(string $tag): bool;
public function clear(): bool;
```

### `#[Cacheable]` attribute

```php
#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    public function __construct(public int $ttl, public array $tags = []) {}
}
```

### `CacheKey`

```php
public static function fromRequest(Request $request): self;
public static function normalizeQuery(string $rawQuery): string;
public function hash(): string;
```

### `CachePolicy`

```php
public function __construct(public int $ttl, public array $tags) {}
```

### CLI commands

| Command | Description |
|---|---|
| `page-cache:clear` | Clear all cached pages |
| `page-cache:purge <target> [--tag]` | Purge a URL or all entries for a tag |
| `page-cache:status` | Show active driver and storage path |

## Documentation

Full usage, API reference, and examples: [marko/page-cache](https://marko.build/docs/packages/page-cache/)
