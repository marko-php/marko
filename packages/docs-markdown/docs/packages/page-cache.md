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

### Cache Lifetime (TTL)

The effective TTL of a stored page is worked out in two steps:

1. A positive `#[Cacheable(ttl: ...)]` is used as-is (seconds).
2. `#[Cacheable(ttl: 0)]` falls back to `page-cache.default_ttl` (`PAGE_CACHE_TTL`, default `3600`).

When the effective TTL is `0` --- that is, `PAGE_CACHE_TTL=0` and the attribute uses `ttl: 0` --- the page **never expires**. It is served until it is removed by a tag purge (`purgeTag()`, which [marko/page-cache-entity](/docs/packages/page-cache-entity/) calls when an entity is saved or deleted), a URL purge (`purgeUrl()` / `marko page-cache:purge <url>`), or `marko page-cache:clear`. This suits content that is purged when it changes.

A negative TTL is always a mistake and fails loudly with a `PageCacheException`:

- A negative `#[Cacheable]` ttl throws when routes are discovered, at application boot.
- A negative `page-cache.default_ttl` throws when the driver first reads it, on the first cache miss of a cacheable route.

### Cookies Are Never Cached

Responses carrying any cookie --- whether attached via `Response::withCookie()` or set directly with a raw `Set-Cookie` header --- are never cached. This is a deliberate security boundary, not a limitation to work around: a cached `Set-Cookie` would be replayed to every later visitor, leaking one user's session (or any other cookie) to everybody else. This includes responses that set analytics or session cookies --- if your response carries any cookie, it bypasses the cache entirely.

### Streamed Responses Are Never Cached

A response that implements routing's [`StreamedResponseInterface`](/docs/packages/routing/#file-and-streamed-responses) (a `FileResponse`, a `StreamedResponse`, an SSE `StreamingResponse`) writes its body while it is sent and has an empty `body()`. `CacheabilityChecker::isResponseCacheable()` returns `false` for it, so a `#[Cacheable]` route that returns a file or a stream is served normally and never cached as an empty page.

### Cache Hits Skip Route Middleware

`PageCacheMiddleware` is global middleware, so it runs **before any route middleware**. A cache hit is returned straight away: route-level middleware such as `AuthMiddleware` or `AuthorizationMiddleware` never runs for it. Only put `#[Cacheable]` on pages that are the same for every visitor.

To make that mistake hard to ship, the application fails at boot with a `PageCacheException` when a `#[Cacheable]` route (on a cacheable method) uses route middleware whose short class name matches `page-cache.auth_middleware_patterns` (default `['*Auth*']`, case-insensitive). The check skips routes that exclude `PageCacheMiddleware` or the matched middleware via `withoutMiddleware`. If a middleware matches the pattern but does not authenticate, narrow the patterns; set them to `[]` to turn the check off.

### Logged-In Visitors Bypass the Cache

A request that carries credentials is never served from the cache and its response is never stored. That covers a request with:

- an `Authorization` header (or the server's `PHP_AUTH_USER` / `PHP_AUTH_DIGEST` / `REDIRECT_HTTP_AUTHORIZATION`)
- the session cookie --- when `marko/session` is installed, the name in `session.cookie.name` is always included, even if you rename it
- any cookie matching `page-cache.bypass_cookies` (fnmatch patterns; default `['marko_session', 'remember_*']`, which covers the `marko/authentication` remember-me cookies)

A request that starts a session gets a `Set-Cookie` on its response, which is never stored (see below); a request that resumes one carries the session cookie and bypasses the cache. Together these keep one user's page --- their name, their orders, their CSRF token --- out of every other visitor's response. Add your own auth cookie names to `bypass_cookies` when you authenticate with a different cookie.

### Query Parameters Are Ignored Unless Listed

Only the query parameters a route lists in `#[Cacheable(query: [...])]` form part of its cache key. Every other parameter is ignored: `/blog?utm_source=mail` and `/blog?x=123` are served the same cached page as `/blog`. This stops arbitrary query strings from minting a new cache entry per request.

List each parameter that changes the page:

```php
#[Get('/blog')]
#[Cacheable(ttl: 600, tags: ['posts'], query: ['page', 'sort'])]
public function index(): Response
{
    // /blog?page=2&sort=new and /blog?page=3 are cached separately;
    // /blog?page=2&sort=new&ref=x shares the entry for /blog?page=2&sort=new.
}
```

A route that reads a query parameter it does not list will serve one visitor's variant to everyone, so list every parameter the page depends on (or leave the action uncached). Each entry must be a non-empty parameter name; anything else throws a `PageCacheException` when routes are discovered.

Allowlisted values are still client-controlled, so `page-cache.max_variants_per_path` (default `1000`) caps how many entries one URL path can hold, counting every query variant, host, scheme and method together. Once a path is at the cap, further variants are served uncached until existing entries are purged, cleared or expire. Set it to `0` for no limit.

### Cache Keys Include Scheme and Host

Entries are keyed by method, scheme, host, path and the normalized allowlisted query parameters, so `http://` and `https://` pages and pages for different hosts are cached separately. The scheme comes from the server's `HTTPS` / `REQUEST_SCHEME` variables; forwarded headers such as `X-Forwarded-Proto` are ignored. The host comes from the `Host` header (falling back to `SERVER_NAME`), lowercased, with the scheme's default port dropped.

The `Host` header is sent by the client. List your real host names in `page-cache.trusted_hosts` (fnmatch patterns such as `example.com` or `*.example.com`, matched without the port) and requests for any other host bypass the cache entirely. With an empty list every host is cached under its own key.

### Extending Cacheability Rules

`CacheabilityChecker` determines whether a given request/response pair is eligible for caching. Override it via a [Preference](/docs/packages/core/) to add custom rules --- for example, skipping the cache based on a custom request header:

```php
use Marko\Core\Attributes\Preference;
use Marko\PageCache\CacheabilityChecker;
use Marko\Routing\Http\Request;

#[Preference(replaces: CacheabilityChecker::class)]
class PreviewAwareCacheabilityChecker extends CacheabilityChecker
{
    public function isRequestCacheable(Request $request): bool
    {
        if ($request->header('X-Preview') !== null) {
            return false;
        }

        return parent::isRequestCacheable($request);
    }
}
```

Call `parent::isRequestCacheable()` so the credential, cookie and trusted-host rules above still apply.

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
use Marko\Config\Env;

return [
    'driver' => Env::string('PAGE_CACHE_DRIVER', 'file'),
    'path' => Env::string('PAGE_CACHE_PATH', 'storage/page-cache'),
    'default_ttl' => Env::int('PAGE_CACHE_TTL', 3600, min: 0),
    'max_variants_per_path' => 1000,
    'cacheable_status_codes' => [200, 301],
    'cacheable_methods' => ['GET', 'HEAD'],
    'bypass_cookies' => ['marko_session', 'remember_*'],
    'trusted_hosts' => [],
    'auth_middleware_patterns' => ['*Auth*'],
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `driver` | `PAGE_CACHE_DRIVER` | `file` | Driver name |
| `path` | `PAGE_CACHE_PATH` | `storage/page-cache` | Root storage directory |
| `default_ttl` | `PAGE_CACHE_TTL` | `3600` | TTL in seconds used when `#[Cacheable]` has `ttl: 0`. `0` means pages never expire and are only removed by a purge or `page-cache:clear`. Negative values throw a `PageCacheException`. A `PAGE_CACHE_TTL` that is not a non-negative integer (`abc`, `1h`, `1.5`, `-1`) fails config load with a `ConfigException` rather than silently becoming `0`. |
| `max_variants_per_path` | --- | `1000` | Maximum cached entries per URL path (all query variants, hosts, schemes and methods). Further variants of a path at the limit are served uncached. `0` disables the limit; negative values throw a `PageCacheException`. See [Query Parameters Are Ignored Unless Listed](#query-parameters-are-ignored-unless-listed). |
| `cacheable_status_codes` | --- | `[200, 301]` | Response status codes eligible for caching |
| `cacheable_methods` | --- | `['GET', 'HEAD']` | Request methods eligible for caching |
| `bypass_cookies` | --- | `['marko_session', 'remember_*']` | Cookie names (fnmatch patterns) that make a request bypass the cache. `session.cookie.name` is added automatically when `marko/session` is installed. See [Logged-In Visitors Bypass the Cache](#logged-in-visitors-bypass-the-cache). |
| `trusted_hosts` | --- | `[]` | Host names (fnmatch patterns) the cache serves; other hosts bypass it. Empty allows every host. Also the hosts a relative `purgeUrl()` purges. |
| `auth_middleware_patterns` | --- | `['*Auth*']` | Middleware short class name patterns that fail boot when used on a `#[Cacheable]` route. `[]` disables the check. |

## CLI Commands

| Command | Description |
|---|---|
| `marko page-cache:clear` | Clear all cached pages |
| `marko page-cache:purge <url>` | Purge a single URL |
| `marko page-cache:purge --tag <tag>` | Purge all entries for a tag |
| `marko page-cache:status` | Show active driver and storage path |

### Examples

```bash
# Show current driver and storage path
marko page-cache:status

# Clear all cached pages
marko page-cache:clear

# Purge a single URL (both http:// and https:// entries for that host)
marko page-cache:purge https://example.com/products/42

# Purge a relative URL for every exact host in page-cache.trusted_hosts
marko page-cache:purge /products/42

# Purge all entries tagged with a given tag
marko page-cache:purge --tag products
```

## API Reference

### PageCacheInterface

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

`lookup()` receives the route's `#[Cacheable(query: [...])]` allowlist and `store()` receives it as `CachePolicy::$queryParams`; drivers build keys with `CacheKey::fromRequest($request, $queryParams)` so unlisted parameters never reach the key. Drivers that store entries themselves should enforce `page-cache.max_variants_per_path` in `store()` and return the response unchanged when a path is at the limit. Pass `purgeUrl()` the URL with only its allowlisted parameters.

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
        public array $query = [],
    ) {}
}
```

`ttl` is in seconds; `0` falls back to `page-cache.default_ttl` and a negative value throws `PageCacheException` (see [Cache Lifetime (TTL)](#cache-lifetime-ttl)). The optional `provider` parameter accepts a class name implementing `CacheTagProviderInterface`. When set, the provider is resolved via the DI container at request time and its returned tags are appended to the static `tags` array (deduplicated). `query` lists the query parameter names that form part of the cache key; all other parameters are ignored (see [Query Parameters Are Ignored Unless Listed](#query-parameters-are-ignored-unless-listed)).

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

public function __construct(string $method, string $scheme, string $host, string $path, string $query);
public static function fromRequest(Request $request, array $queryParams): self;
public static function schemeFromRequest(Request $request): string;
public static function hostnameFromRequest(Request $request): string;
public static function normalizeHost(string $host, string $scheme): string;
public static function normalizeQuery(string $rawQuery): string;
public function hash(): string;
```

`fromRequest()` keeps only the query parameters named in `$queryParams`. The hash covers method, scheme, host, path and query (see [Cache Keys Include Scheme and Host](#cache-keys-include-scheme-and-host)). Drivers that purge by URL build keys with `normalizeHost()` so a purge matches the stored host exactly.

`normalizeQuery()` is used by both store and purge operations. It parses the raw query string with `parse_str`, sorts keys, and re-encodes with RFC 3986 percent-encoding (`http_build_query(..., PHP_QUERY_RFC3986)`). This ensures that a URL stored with a space (`q=hello%20world`) and a URL purged with a `+` (`q=hello+world`) hash to the same cache key, so purge-by-URL never silently misses.

### CachePolicy

```php
use Marko\PageCache\CachePolicy;

public function __construct(public int $ttl, public array $tags, public array $queryParams = []) {}
```

### PageCacheConfig

```php
use Marko\PageCache\Config\PageCacheConfig;

public function driver(): string;
public function path(): string;
public function defaultTtl(): int;
public function cacheableStatusCodes(): array;
public function cacheableMethods(): array;
public function bypassCookies(): array;
public function trustedHosts(): array;
public function maxVariantsPerPath(): int;
public function authMiddlewarePatterns(): array;
```

`defaultTtl()` throws `PageCacheException` when `page-cache.default_ttl` is negative; `maxVariantsPerPath()` throws when `page-cache.max_variants_per_path` is negative.

### Exceptions

| Exception / Factory | Description |
|---|---|
| `PageCacheException` | Base exception for all page-cache errors |
| `NoDriverException` | Thrown when no driver is bound to `PageCacheInterface` |
| `PageCacheException::negativeTtl()` | Thrown when a `#[Cacheable]` attribute declares a negative `ttl` (at route discovery) |
| `PageCacheException::invalidQueryParam()` | Thrown when a `#[Cacheable]` `query` entry is not a non-empty string (at route discovery) |
| `PageCacheException::negativeMaxVariantsPerPath()` | Thrown when `page-cache.max_variants_per_path` is negative |
| `PageCacheException::negativeDefaultTtl()` | Thrown when `page-cache.default_ttl` (`PAGE_CACHE_TTL`) is negative |
| `PageCacheException::invalidTagProvider()` | Thrown when the class named in `provider` does not implement `CacheTagProviderInterface` |
| `PageCacheException::cacheableRouteWithAuthMiddleware()` | Thrown at boot when a `#[Cacheable]` route uses middleware matching `page-cache.auth_middleware_patterns` |
| `PageCacheException::purgeUrlWithoutHost()` | Thrown by `purgeUrl()` for a relative URL when `page-cache.trusted_hosts` lists no exact host |
| `PageCacheException::missingEntityBridge()` | Thrown at boot when a class implements `IdentityInterface` but `marko/page-cache-entity` is not installed |

## Related Packages

- [marko/page-cache-file](/docs/packages/page-cache-file/) --- File-based driver implementation
- [marko/page-cache-entity](/docs/packages/page-cache-entity/) --- Auto-purge page-cache tags when entities change
