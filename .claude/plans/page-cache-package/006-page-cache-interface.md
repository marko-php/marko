# Task 006: PageCacheInterface Contract

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Define the `PageCacheInterface` contract that all drivers implement. Five methods: `lookup`, `store`, `purgeUrl`, `purgeTag`, `clear`. Document the contract carefully — including that `lookup` may always return `null` for reverse-proxy drivers, and `store` may return a decorated `Response`.

## Context
- Related files:
  - `packages/cache/src/Contracts/CacheInterface.php` (template — see `@throws` style and PHPDoc structure)
- Patterns to follow:
  - PHPDoc on every method explaining behavior
  - Multi-line PHPDoc style per `sibling-modules.md`
  - `@throws` documented for any exception drivers may raise
  - All parameter and return types declared

## Requirements (Test Descriptions)

These tests verify the contract via reflection — no implementation exists yet at this stage (drivers come later).

- [ ] `it declares lookup with Request parameter and nullable Response return`
- [ ] `it declares store with Request, Response, and CachePolicy parameters returning Response`
- [ ] `it declares purgeUrl with string parameter returning bool`
- [ ] `it declares purgeTag with string parameter returning bool`
- [ ] `it declares clear with no parameters returning bool`

## Acceptance Criteria
- `src/Contracts/PageCacheInterface.php` defines all five methods with declared types
- PHPDoc on each method must include:
  - `lookup`: documents that drivers MAY return `null` unconditionally for reverse-proxy drivers (Varnish, NGINX FastCGI cache, Cloudflare/Fastly) where the proxy intercepts hits before PHP runs.
  - `store`: documents that the returned `Response` MAY differ from the input — proxy drivers add headers like `Cache-Control`, `Surrogate-Control`, `Surrogate-Key`/`Cache-Tag` here. Internal-storage drivers (file, Redis, db) MUST return the input `Response` unchanged in v1. The middleware uses the returned `Response` (not the input) for sending downstream so header decoration is honored.
  - `purgeUrl`: documents v1 limitation — purges only the canonical GET key for the given URL. HEAD entries and future Vary-axis variants of the same URL are NOT purged in v1. Drivers that add Vary support later MUST extend the contract with a separate purge-variants method, not change this method's semantics.
  - `purgeTag`: documents that drivers MAY purge eagerly (file: walk the index now) or lazily (Varnish: send a BAN request). Returns `bool` indicating dispatch success, not necessarily completion.
  - `clear`: documents that drivers SHOULD remove all state (file: page files + tag index files; proxy: full cache flush). Returns `bool`.
- Reflection-based tests in `tests/Unit/Contracts/PageCacheInterfaceTest.php` confirm the signatures
- Strict types declared

## Implementation Notes
(Left blank — filled in by programmer during implementation)
