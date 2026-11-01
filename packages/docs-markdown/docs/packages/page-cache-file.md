---
title: marko/page-cache-file
description: File-based full-page cache driver — stores cached HTTP responses on disk with tag-based invalidation and atomic writes.
---

File-based full-page cache driver --- stores cached HTTP responses on disk with tag-based invalidation and atomic writes. Implements `PageCacheInterface` from `marko/page-cache` using the local filesystem. Cached responses are serialized under `storage/page-cache/pages/`. Tag-based invalidation uses a directory of empty marker files per tag under `storage/page-cache/tags/`, so tagging a page never reads, rewrites or locks a shared index. Writes are atomic to prevent partial reads under concurrent traffic. The driver is automatically wired via `module.php` --- no manual container binding is needed.

Implements `PageCacheInterface` from `marko/page-cache`.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

This automatically installs `marko/page-cache`.

## Configuration

```php title="config/page-cache.php"
use Marko\Config\Env;

return [
    'driver' => Env::string('PAGE_CACHE_DRIVER', 'file'),
    'path' => Env::string('PAGE_CACHE_PATH', 'storage/page-cache'),
    'default_ttl' => Env::int('PAGE_CACHE_TTL', 3600, min: 0),
    'cacheable_status_codes' => [200, 301],
    'cacheable_methods' => ['GET', 'HEAD'],
];
```

A relative `path` resolves against the project root; an absolute path (Unix, Windows drive or UNC) is used as given. The `path` directory and its subdirectories are created automatically if they do not exist.

`default_ttl` is used when a `#[Cacheable]` attribute has `ttl: 0`. When the effective TTL is `0` (for example `PAGE_CACHE_TTL=0`), the page is stored with no expiry and served until it is purged by tag or URL or cleared with `marko page-cache:clear`. A `PAGE_CACHE_TTL` that is not a non-negative integer (`abc`, `1h`, `1.5`, `-1`) fails config load with a `ConfigException` instead of silently becoming `0`. See [Cache Lifetime (TTL)](/docs/packages/page-cache/#cache-lifetime-ttl).

### Storage Layout

```
storage/page-cache/
  pages/{hash}.cache                 # Serialized cached response
  tags/{tag-hash}/{page-hash}        # Empty marker: the page carries the tag
  variants/{path-hash}/{page-hash}   # Empty marker: the page is a variant of the path
```

Each `.cache` file contains the serialized response body, status code, headers, associated tags, and expiry timestamp (`null` for a page that never expires). An expired entry is deleted the next time it is looked up.

Tagging a page creates one empty file in each of its tag directories, so `store()` costs the same however many pages share a tag and takes no lock. `purgeTag()` deletes every page named in the tag's directory, then the directory. A `tags/{hash}.tag` index left by an earlier version of the driver is still honored by `purgeTag()` and removed by `clear()`.

The `variants/` markers enforce `page-cache.max_variants_per_path`: storing a page that is new to its path first counts the path's markers, and when the path is at the limit, sweeps markers whose page is gone and skips the store if it is still full. A page already cached for the path is always refreshed. Concurrent stores may briefly overshoot the limit.

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

public function lookup(Request $request, array $queryParams): ?Response;
public function store(Request $request, Response $response, CachePolicy $policy): Response;
public function purgeUrl(string $url): bool;
public function purgeTag(string $tag): bool;
public function clear(): bool;
```

### Storage Details

- Each cache key is derived from the request method, scheme, host, path and the route's allowlisted query parameters (`#[Cacheable(query: [...])]`), normalized and hashed.
- `purgeUrl()` removes the GET entry for both `http` and `https`. An absolute URL purges its own host; a relative URL purges every exact host in `page-cache.trusted_hosts` and throws a `PageCacheException` when there is none.
- Writes use a temp file with `LOCK_EX` followed by an atomic `rename()` to prevent corruption under concurrent traffic.
- Tag membership is one empty marker file per page per tag, created on each `store()` with no shared index or lock.
- Expired entries are removed on the next `lookup()` call for that key (lazy expiration).
- Expiry and `created_at` timestamps come from the PSR-20 `ClockInterface` ([`marko/clock`](/docs/packages/clock/)), not `time()`, so the driver follows a [`FakeClock`](/docs/packages/testing/#fakeclock) in tests.

## Related Packages

- [marko/page-cache](/docs/packages/page-cache/) --- Interface package with contracts, middleware, and CLI commands
