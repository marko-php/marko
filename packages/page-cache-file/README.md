# marko/page-cache-file

File-based full-page cache driver — stores cached responses on disk with tag-based invalidation.

## Overview

`marko/page-cache-file` implements `PageCacheInterface` using the local filesystem. Cached responses are serialized and stored under `storage/page-cache/pages/`. Tag-based invalidation is supported via a reverse-index file per tag stored under `storage/page-cache/tags/`. Writes are atomic to prevent partial reads under concurrent traffic.

The driver is automatically wired via `module.php` — no manual container binding is needed.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

## Usage

Once both packages are installed the driver is active. Annotate controller actions with `#[Cacheable]` to opt them in to caching:

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

See [marko/page-cache](https://marko.build/docs/packages/page-cache/) for full usage examples, CLI commands, and customization options.

## Configuration

The driver reads from `config/page-cache.php`:

| Env var | Default | Description |
|---|---|---|
| `PAGE_CACHE_DRIVER` | `file` | Driver name |
| `PAGE_CACHE_PATH` | `storage/page-cache` | Root storage directory |
| `PAGE_CACHE_TTL` | `3600` | Default TTL in seconds |

### Storage layout

```
storage/page-cache/
  pages/{hash}.cache     # Serialized cached response
  tags/{hash}.tag        # Reverse-index: page hashes per tag
```

Each `.cache` file contains the serialized response body, status code, headers, associated tags, and expiry timestamp. Each `.tag` file contains a serialized list of page hashes that carry that tag, used to resolve purge-by-tag requests.

## API Reference

`FilePageCacheDriver` implements `Marko\PageCache\Contracts\PageCacheInterface`:

```php
public function lookup(Request $request): ?Response;
public function store(Request $request, Response $response, CachePolicy $policy): Response;
public function purgeUrl(string $url): bool;
public function purgeTag(string $tag): bool;
public function clear(): bool;
```

See [marko/page-cache](https://marko.build/docs/packages/page-cache/) for the full interface documentation.

## Documentation

Full usage, API reference, and examples: [marko/page-cache-file](https://marko.build/docs/packages/page-cache-file/)
