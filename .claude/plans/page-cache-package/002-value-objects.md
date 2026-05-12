# Task 002: Value Objects (CacheKey, CachePolicy)

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the two readonly value objects used by the page cache: `CacheKey` (encapsulates the request identity for cache lookup) and `CachePolicy` (encapsulates TTL + tags for storage).

## Context
- Related files:
  - `packages/cache/src/CacheItem.php` (template — readonly value object with named static factories)
  - `packages/routing/src/Http/Request.php` (CacheKey is derived from)
- Patterns to follow:
  - `readonly class` (all properties immutable)
  - Constructor property promotion
  - Named static factory: `CacheKey::fromRequest(Request)`
  - Hashing: `hash('xxh128', ...)` (matches `FileCacheDriver::hashKey`)

## Requirements (Test Descriptions)
- [ ] `it builds a cache key from method, path, and query string`
- [ ] `it normalizes query string to sorted key-value pairs`
- [ ] `it produces an empty query for requests without query parameters`
- [ ] `it returns the same hash for two equivalent keys with different query orderings`
- [ ] `it returns different hashes for different methods on the same path`
- [ ] `it exposes a public static normalizeQuery helper that sorts query parameters by key`
- [ ] `it returns an empty string from normalizeQuery when given an empty string`
- [ ] `it preserves query parameter values verbatim during normalization (does not URL-decode)`
- [ ] `it builds a cache policy from ttl and tags`
- [ ] `it accepts an empty tags array on a cache policy`

## Acceptance Criteria
- `src/CacheKey.php` is a `readonly class` with public properties `method`, `path`, `query`, plus `fromRequest(Request)` static factory, `normalizeQuery(string): string` public static helper, and `hash(): string` method
- `src/CachePolicy.php` is a `readonly class` with public properties `ttl: int` and `tags: array<string>`
- `fromRequest()` MUST extract the query string from `$_SERVER['QUERY_STRING']`-equivalent on the `Request`. **Note**: `Request::query()` returns the parsed `$_GET` array, not the raw query string. Either re-encode via `http_build_query($request->query())` after sorting, or parse `$request->path()`'s URI suffix (the framework's `Request::path()` strips query already — check `packages/routing/src/Http/Request.php`). Recommended: use `ksort($queryArray)` then `http_build_query($sortedArray)` for a deterministic representation. Document the chosen approach so `purgeUrl` can replicate it byte-for-byte.
- All tests passing in `tests/Unit/CacheKeyTest.php` and `tests/Unit/CachePolicyTest.php`
- No mutable state on either class
- All `@throws` tags present where applicable (none expected here)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
