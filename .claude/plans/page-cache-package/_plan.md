# Plan: page-cache Packages (Interface + File Driver)

## Created
2026-05-09

## Status
completed

## Objective
Build `marko/page-cache` (interface package) and `marko/page-cache-file` (file driver) — a full-page HTTP response cache with attribute-driven opt-in (`#[Cacheable]`), tag-based invalidation, and a middleware that intercepts requests to serve or store cached responses.

## Related Issues
none

## Discovery Notes

**Existing patterns followed:** Mirrors `marko/cache` + `marko/cache-file` package layout. Driver uses `Marko\PageCache\File\` namespace and `File*` class prefix per `sibling-modules.md`. Interface package contains `Contracts/`, `Config/`, `Command/`, `Exceptions/`, value objects, attribute, middleware, and a cacheability checker. Driver package contains only the driver class plus `module.php` binding.

**Why a new package, not extending `marko/cache`:** The existing `CacheInterface` is a key-value item cache (`get/set/has` by string key with TTL). Full page cache has fundamentally different semantics — request → response, tag-based purging, cacheability rules tied to HTTP semantics, header emission for reverse-proxy drivers — and warrants its own contract.

**Driver-divergence analysis (informs interface shape):**

| Driver type | Behavior |
|---|---|
| Internal storage (file, Redis, db) | PHP looks up cached response, serves on hit, stores after generation. Implements full read/write/purge. |
| Reverse proxy (Varnish, NGINX FastCGI cache, Cloudflare/Fastly) | Never reaches PHP on a cache hit. PHP-side responsibility: emit `Cache-Control`/`Surrogate-Control`/`Cache-Tag` headers; handle purges via BAN/PURGE HTTP requests to the proxy. |

**Interface shape** accommodates both: `lookup()` may always return `null` for proxy drivers; `store()` returns a (possibly decorated) `Response` so proxy drivers can emit headers; `purgeUrl`/`purgeTag` are the only writes a proxy driver actually performs.

**Integration:** `PageCacheMiddleware` is registered as **global middleware** by adding its FQCN to the hardcoded `Application::GLOBAL_MIDDLEWARE` constant in `packages/core/src/Application.php` (the same way `SessionMiddleware` and `LayoutMiddleware` are registered). Class-existence guards in `discoverGlobalMiddleware()` ensure the entry is silently skipped when `marko/page-cache` is not installed. It re-matches the request via `RouteMatcherInterface` to find the controller/action, reflects on the action method for `#[Cacheable]`, and:
- If no attribute → pass through (no caching for this route).
- If attribute present and request is cacheable → call `lookup()`; on hit, return cached response; on miss, run pipeline and call `store()` if the response is cacheable.

**Plugin interception:** `Router::resolveParameters()` already unwraps `PluginInterceptedInterface` controllers via `getPluginTarget()` before reflecting. `CacheabilityChecker::getRouteAttribute()` reflects on `$matched->route->controller` (a class-string from the route definition, not a resolved instance), so it does not encounter interceptor wrappers. Reflection on the class-string targets the original controller directly — interception is irrelevant at this layer.

**Resolved decisions from discovery:**
- Package names: `marko/page-cache` + `marko/page-cache-file`
- Cacheability model: attribute-driven, opt-in. `#[Cacheable(ttl: int, tags: array)]` on controller methods.
- Tag-based purging in v1: yes, file driver maintains a reverse index (`tags/{tag-hash}.tag` files listing page keys per tag).
- Cache-key axes in v1: URL path + sorted query string + HTTP method. No Vary headers, no scope-aware variation.
- ESI / hole-punching: deferred to a later plan.

## Scope

### In Scope
- `marko/page-cache` interface package containing:
  - `PageCacheInterface` contract (`lookup`, `store`, `purgeUrl`, `purgeTag`, `clear`)
  - `#[Cacheable(ttl, tags)]` attribute (TARGET_METHOD)
  - Value objects: `CacheKey` (URL+query+method, hashed), `CachePolicy` (ttl + tags)
  - `PageCacheConfig` + `config/page-cache.php` (driver name, storage path, default TTL, cacheable status codes)
  - `CacheabilityChecker` service (decides if request/response is cacheable: GET/HEAD only, status code in allowlist, no `Set-Cookie`, no `Cache-Control` containing the `no-store` or `private` directive — parsed as comma-separated, case-insensitive directives, not an exact-string match)
  - `PageCacheMiddleware` (registered globally; orchestrates lookup/store, reflects on matched route for `#[Cacheable]`)
  - CLI commands: `page-cache:clear`, `page-cache:purge`, `page-cache:status`
  - Exceptions: `PageCacheException`, `NoDriverException`
- `marko/page-cache-file` driver package containing:
  - `FilePageCacheDriver` implementing `PageCacheInterface`
  - Atomic file writes (.tmp + rename, matching `cache-file` pattern)
  - Tag reverse-index files for tag-based purging
  - `module.php` binding `PageCacheInterface` → `FilePageCacheDriver`
- Tests: package structure, unit tests for value objects, attribute, config, cacheability checker, middleware, CLI commands, and the file driver (including tag purge roundtrips). All tests run without external services.
- Monorepo integration: root `composer.json` autoload entries, default branch alias entries.
- READMEs for both packages following `code-standards.md` Package README Standards.

### Out of Scope
- Redis, memcached, database, Varnish, CDN drivers (separate plans).
- ESI / hole-punching / dynamic fragment caching.
- Vary by header/cookie/scope-tenant. Cache key in v1 is URL + sorted query + method only.
- Stale-while-revalidate semantics.
- Conditional requests (ETag, Last-Modified, 304 Not Modified) — handled by HTTP layer, not FPC.
- Auto-purge on entity changes (event-driven invalidation hooks). Application code calls `purgeTag()` directly for v1.
- `marko/framework` metapackage update (no need to bundle FPC by default; users opt in).

## Success Criteria
- [ ] `PageCacheInterface` defines `lookup`, `store`, `purgeUrl`, `purgeTag`, `clear` with documented contracts
- [ ] `#[Cacheable(ttl, tags)]` attribute readable via reflection on controller methods
- [ ] `PageCacheMiddleware` serves cache hits and stores cache misses on routes with `#[Cacheable]`
- [ ] `FilePageCacheDriver` implements all five interface methods with passing unit tests
- [ ] Tag-based purging deletes all pages tagged with the purged tag
- [ ] CLI commands `page-cache:clear`, `page-cache:purge {target}`, `page-cache:status` work end-to-end
- [ ] All tests pass (`composer test`)
- [ ] Linting passes (`./vendor/bin/phpcs && ./vendor/bin/php-cs-fixer fix`)
- [ ] Both packages have READMEs following Package README Standards
- [ ] Root composer autoload entries added; `composer dump-autoload` succeeds
- [ ] `PageCacheMiddleware::class` is appended to `Application::GLOBAL_MIDDLEWARE`; `class_exists` guard keeps it inert when the package is not installed

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | `marko/page-cache` package scaffolding (composer.json, root autoload, Pest.php, package structure tests) | none | completed |
| 002 | Value objects: `CacheKey`, `CachePolicy` | 001 | completed |
| 003 | `#[Cacheable]` attribute | 001 | completed |
| 004 | Exceptions: `PageCacheException`, `NoDriverException` | 001 | completed |
| 005 | `PageCacheConfig` + `config/page-cache.php` | 001 | completed |
| 006 | `PageCacheInterface` contract | 002, 004 | completed |
| 007 | `CacheabilityChecker` service | 003, 005 | completed |
| 008 | `PageCacheMiddleware` (global, reflects on matched route for `#[Cacheable]`) | 003, 006, 007 | completed |
| 009 | CLI commands: `page-cache:clear`, `page-cache:purge`, `page-cache:status` | 005, 006 | completed |
| 010 | `marko/page-cache-file` package scaffolding (composer.json, module.php, root autoload, structure tests) | 005, 006 | completed |
| 011 | `FilePageCacheDriver` core ops (lookup, store, purgeUrl, clear) | 006, 010 | completed |
| 012 | `FilePageCacheDriver` tag indexing (purgeTag + reverse-index persistence) | 011 | completed |
| 013 | README for `marko/page-cache` | 002, 003, 004, 005, 006, 007, 008, 009 | completed |
| 014 | README for `marko/page-cache-file` | 010, 011, 012 | completed |
| 015 | Register `PageCacheMiddleware` in `Application::GLOBAL_MIDDLEWARE` | 008 | completed |
| 016 | Secure storage path resolution in `FilePageCacheDriver` (resolve relative paths against project base) | 011, 012 | completed |

## Architecture Notes

### Interface Contract (`PageCacheInterface`)

```php
interface PageCacheInterface
{
    /**
     * Look up a cached response for this request.
     * Returns null on miss. Reverse-proxy drivers (Varnish) may always return null.
     */
    public function lookup(Request $request): ?Response;

    /**
     * Store a response in the cache. Returns a (possibly decorated) Response —
     * proxy drivers may add Cache-Control/Surrogate-Control headers here.
     */
    public function store(
        Request $request,
        Response $response,
        CachePolicy $policy,
    ): Response;

    /**
     * Purge a specific URL from the cache.
     */
    public function purgeUrl(string $url): bool;

    /**
     * Purge all cached responses tagged with this tag.
     */
    public function purgeTag(string $tag): bool;

    /**
     * Clear all cached responses.
     */
    public function clear(): bool;
}
```

### Cache Key Derivation (v1)

```php
readonly class CacheKey
{
    public function __construct(
        public string $method,    // GET, HEAD
        public string $path,      // /products/42
        public string $query,     // sorted query string, '' if none
    ) {}

    public static function fromRequest(Request $request): self;
    public function hash(): string;  // xxh128 of "method|path|query"
}
```

Sorted query string ensures `?a=1&b=2` and `?b=2&a=1` map to the same key.

### Middleware Flow

```php
public function handle(Request $request, callable $next): Response
{
    if (!$this->cacheabilityChecker->isRequestCacheable($request)) {
        return $next($request);
    }

    $cacheable = $this->cacheabilityChecker->getRouteAttribute($request);
    if ($cacheable === null) {
        return $next($request);
    }

    $hit = $this->pageCache->lookup($request);
    if ($hit !== null) {
        return $hit;
    }

    $response = $next($request);

    if (!$this->cacheabilityChecker->isResponseCacheable($response)) {
        return $response;
    }

    return $this->pageCache->store(
        $request,
        $response,
        new CachePolicy(ttl: $cacheable->ttl, tags: $cacheable->tags),
    );
}
```

`CacheabilityChecker` injects `RouteMatcherInterface` and reflects on `$matched->route->controller::$matched->route->action` to find `#[Cacheable]`. Re-matching is cheap and avoids cross-package coupling to Router internals.

### File Driver Storage Layout

```
storage/page-cache/
  pages/{hash}.cache       # Serialized payload: status, body, headers, tags, expires_at, created_at
  tags/{tag-hash}.tag      # Serialized list of page hashes that carry this tag
```

**Atomic writes:** Write to `*.tmp.{uniqid}`, then `rename()` — same pattern as `FileCacheDriver`.

**Storing:** Write page file + for each tag in policy, append page hash to `tags/{tag-hash}.tag` (read existing list, add hash, write back atomically; deduplicate).

**Purge by tag:** Read `tags/{tag-hash}.tag`, delete each listed `pages/{hash}.cache`, delete the tag file. Best-effort if some files already gone.

**Purge by URL:** Compute `CacheKey::fromRequest()` for `[GET, $path, $query]`; delete `pages/{hash}.cache`. Note: in v1, only matches the canonical GET key — query-string variants of the same path are not auto-purged unless the caller passes the full URL.

**Stale-tag-pointer cleanup:** When `lookup` finds an expired entry, it deletes the page file. The tag indexes may then point to non-existent files; `purgeTag` tolerates missing files. A `page-cache:gc` command is **out of scope for v1** but worth a future plan.

### Config Defaults

```php
// config/page-cache.php
return [
    'driver' => $_ENV['PAGE_CACHE_DRIVER'] ?? 'file',
    'path' => $_ENV['PAGE_CACHE_PATH'] ?? 'storage/page-cache',
    'default_ttl' => (int) ($_ENV['PAGE_CACHE_TTL'] ?? 3600),
    'cacheable_status_codes' => [200, 301],
    'cacheable_methods' => ['GET', 'HEAD'],
];
```

Per code standards: no fallback parameters in `getX()` calls; all keys must exist when read.

### CLI Commands

- `page-cache:clear` — clears entire cache
- `page-cache:purge <target>` — defaults to URL purge; `--tag` flag purges by tag instead
- `page-cache:status` — shows driver, storage path, page count, total size (file driver only)

### Dependency Graph

```
                  001
                   │
       ┌───────┬───┴────┬────────┐
       ▼       ▼        ▼        ▼
      002    003      004      005
       │      │        │        │
       └──┬───┘        │        │
          ▼            │        │
         006 ◄─────────┘        │
          │                     │
          │   ┌─────────────────┤
          │   ▼                 │
          │  007 ◄──────────────┤   (007 needs 003 + 005)
          │   │                 │
          ├───┴──────► 008      │
          │                     │
          ├──────────► 009 ◄────┤   (009 needs 005 + 006)
          │                     │
          ├──────────► 010 ◄────┤   (010 needs 005 + 006)
          │              │
          └──────────► 011 ──► 012
                                
        (013 depends on 002-009)  
        (014 depends on 010-012)  
        (015 depends on 008)
```

## Risks & Mitigations

- **Re-matching the route in middleware** is duplicative work (Router already matched). Mitigation: `RouteMatcher` is a regex match against an in-memory collection — measured cost is negligible. If it becomes a hot-path concern, a follow-up plan can introduce a request-scoped `MatchedRouteContext` service in `marko/routing`.
- **Tag index file contention under concurrent writes**: two requests for different cacheable pages with the same tag could race when appending to the same `tags/{tag-hash}.tag` file. Mitigation: read-modify-write under `LOCK_EX` via `flock()`; tolerable for v1 since tag writes are infrequent (only on cache miss + store).
- **Storing all headers** in the cache file may include sensitive ones (`Set-Cookie`, `Authorization`-derived). Mitigation: `CacheabilityChecker::isResponseCacheable()` returns false when `Set-Cookie` is present; an explicit allow/strip list of headers can be added if needed but keeping the rule simple ("any `Set-Cookie` → not cacheable") is the safest v1 default.
- **Cache poisoning via query strings**: an attacker could spam unique query params to fill the cache with junk. Mitigation: TTL-based eviction is the v1 answer; a configurable max-entry count or LRU is out of scope for v1 but a known follow-up.
- **Interface churn risk** if Vary support added later: signatures of `lookup` and `store` already take `Request` and `Response` — adding Vary axes is internal to the cache key derivation, so the public contract should remain stable. `CachePolicy` may grow a `varyOn` field; that's an additive change.
