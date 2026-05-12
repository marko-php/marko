---
title: marko/page-cache-file
description: File-based full-page cache driver — stores cached HTTP responses on disk with tag-based invalidation and atomic writes.
---

File-based full-page cache driver --- stores cached HTTP responses on disk with tag-based invalidation and atomic writes. Implements `PageCacheInterface` from `marko/page-cache` using the local filesystem. Cached responses are serialized under `storage/page-cache/pages/`. Tag-based invalidation uses a reverse-index file per tag stored under `storage/page-cache/tags/`. Writes are atomic to prevent partial reads under concurrent traffic. The driver is automatically wired via `module.php` --- no manual container binding is needed.

Implements `PageCacheInterface` from `marko/page-cache`.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

This automatically installs `marko/page-cache`.

## Configuration

```php title="config/page-cache.php"
return [
    'driver' => env('PAGE_CACHE_DRIVER', 'file'),
    'path'   => env('PAGE_CACHE_PATH', 'storage/page-cache'),
    'ttl'    => (int) env('PAGE_CACHE_TTL', 3600),
];
```

The `path` directory and its subdirectories are created automatically if they do not exist.

### Storage Layout

```
storage/page-cache/
  pages/{hash}.cache     # Serialized cached response
  tags/{hash}.tag        # Reverse-index: page hashes per tag
```

Each `.cache` file contains the serialized response body, status code, headers, associated tags, and expiry timestamp. Each `.tag` file contains a serialized list of page hashes that carry that tag, used to resolve purge-by-tag requests.

## Usage

Once both packages are installed the driver is active. Annotate controller actions with `#[Cacheable]` to opt them in:

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
        return Response::ok($this->productRepository->find($id));
    }
}
```

See [marko/page-cache](/docs/packages/page-cache/) for full usage examples, CLI commands, and customization options.

### When to Use

- **Default choice** for most applications
- No external dependencies
- Data persists across requests and restarts
- Suitable for single-server deployments

## API Reference

`FilePageCacheDriver` implements all methods from `PageCacheInterface`. See [marko/page-cache](/docs/packages/page-cache/) for the full interface documentation.

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

### Storage Details

- Each cache key is derived from the request URL and query string, normalized and hashed.
- Writes use a temp file with `LOCK_EX` followed by an atomic `rename()` to prevent corruption under concurrent traffic.
- Tag entries are similarly written atomically and updated on each `store()` call.
- Expired entries are removed on the next `lookup()` call for that key (lazy expiration).

## Related Packages

- [marko/page-cache](/docs/packages/page-cache/) --- Interface package with contracts, middleware, and CLI commands
