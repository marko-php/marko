# Task 011: FilePageCacheDriver Core Ops (lookup, store, purgeUrl, clear)

**Status**: completed
**Depends on**: 006, 010
**Retry count**: 0

## Description
Implement the four non-tag operations on `FilePageCacheDriver`: `lookup`, `store`, `purgeUrl`, and `clear`. Uses the same atomic-write pattern as `FileCacheDriver`. Tag-related work (storing tag indices, `purgeTag`) is task 012.

## Context
- Related files:
  - `packages/cache-file/src/Driver/FileCacheDriver.php` (template — atomic writes, expiry handling, key hashing)
  - `packages/page-cache/src/Contracts/PageCacheInterface.php` (the contract being implemented)
  - `packages/page-cache/src/CacheKey.php`, `CachePolicy.php`
  - `packages/page-cache/src/Config/PageCacheConfig.php`
- Patterns to follow:
  - `readonly class` constructed from `PageCacheConfig`
  - Hash via `hash('xxh128', ...)` — `CacheKey::hash()` already does this
  - Atomic writes via `*.tmp.{uniqid}` + `rename()`
  - Storage path: `{config->path()}/pages/{hash}.cache`

## Storage Format (single page entry)

```php
[
    'status_code' => int,
    'body' => string,
    'headers' => array<string, string>,
    'tags' => array<string>,         // recorded for later GC; not used in v1 lookup
    'expires_at' => ?int,             // unix timestamp, null = never
    'created_at' => int,
]
```

Serialized via `serialize()` — same as `FileCacheDriver`.

## Requirements (Test Descriptions)
- [ ] `it returns null on lookup when no entry exists for the request`
- [ ] `it returns the stored Response on lookup when the entry is fresh`
- [ ] `it returns null on lookup when the entry has expired and deletes the expired file`
- [ ] `it stores a Response with status code, body, headers, ttl, and tags`
- [ ] `it returns the same Response from store unchanged in v1`
- [ ] `it uses the configured default ttl when CachePolicy ttl equals zero`
- [ ] `it uses an explicit ttl from CachePolicy when greater than zero`
- [ ] `it deletes the corresponding cache file when purgeUrl is called for an existing URL`
- [ ] `it returns true from purgeUrl when no entry exists`
- [ ] `it parses URL paths and query strings consistently between purgeUrl and store (round-trip a stored URL through purgeUrl)`
- [ ] `it normalizes query string ordering when purging by URL (purgeUrl with "?b=2&a=1" purges an entry stored with "?a=1&b=2")`
- [ ] `it deletes all page cache files when clear is called`
- [ ] `it returns true from clear when the cache directory does not exist`

## Acceptance Criteria
- All four methods implemented in `src/Driver/FilePageCacheDriver.php` (`purgeTag` stays a stub throwing `LogicException` — task 012 implements it)
- Atomic writes — no partial files on disk
- Sorted query string in cache key derivation (delegated to `CacheKey::fromRequest()`)
- The `pages/` subdirectory is created on demand
- `clear()` deletes only `*.cache` files in `pages/` (does not touch `tags/` — task 012 handles those)
- Tests in `tests/Unit/Driver/FilePageCacheDriverTest.php` use a temp directory fixture (`sys_get_temp_dir() . '/page-cache-test-' . bin2hex(random_bytes(8))`) cleaned up after each test
- All `@throws` documented (e.g., `ConfigNotFoundException` from config getters)
- Strict types declared

## Implementation Notes
- For the `expires_at` calculation: if `policy->ttl === 0`, use `$config->defaultTtl()` (matching `FileCacheDriver` semantics). If `policy->ttl < 0`, no expiration. If `> 0`, `time() + ttl`.
- `purgeUrl(string $url)`:
  - Parse `$url` with `parse_url()` to extract path + query string components.
  - Sort the query string the same way `CacheKey::fromRequest()` does (same normalization function — extract that into a private static helper on `CacheKey` or duplicate the exact algorithm; either way, the two code paths must produce identical output for the same logical URL).
  - Build a `CacheKey` directly via constructor (`new CacheKey(method: 'GET', path: $path, query: $sortedQuery)`) — do NOT reconstruct a synthetic `Request`. The interface contract for `purgeUrl` only knows about GET (canonical key); HEAD entries with the same path are NOT purged in v1, and that's documented as a known limitation.
  - Document explicitly in `PageCacheInterface::purgeUrl` PHPDoc: "v1 purges only the canonical GET key for the given URL. HEAD entries and future Vary-axis variants are not purged."
- Because both `purgeUrl` and `CacheKey::fromRequest` need identical query-sorting logic, expose a public static `CacheKey::normalizeQuery(string $rawQuery): string` helper as part of task 002. (This is added to task 002 requirements.)
