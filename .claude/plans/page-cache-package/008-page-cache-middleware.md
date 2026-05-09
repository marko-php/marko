# Task 008: PageCacheMiddleware

**Status**: completed
**Depends on**: 003, 006, 007
**Retry count**: 0

## Description
Create `PageCacheMiddleware`, the global middleware that integrates page caching into the routing pipeline. Reads `#[Cacheable]` from the matched route via `CacheabilityChecker`; on hit, returns the cached response; on miss, runs the pipeline and stores cacheable responses.

## Context
- Related files:
  - `packages/routing/src/Middleware/MiddlewareInterface.php` (the contract this implements)
  - `packages/routing/src/Middleware/MiddlewarePipeline.php` (how middleware is invoked)
  - `packages/page-cache/src/Contracts/PageCacheInterface.php` (from task 006)
  - `packages/page-cache/src/CacheabilityChecker.php` (from task 007)
  - `packages/page-cache/src/CachePolicy.php` (from task 002)
- Patterns to follow:
  - `readonly class` implementing `MiddlewareInterface`
  - Constructor injection of `PageCacheInterface` and `CacheabilityChecker`
  - Logic flow per the `_plan.md` "Middleware Flow" section

## Requirements (Test Descriptions)
- [ ] `it passes through when the request is not cacheable by HTTP method`
- [ ] `it passes through when the matched route has no Cacheable attribute`
- [ ] `it returns the cached response when lookup hits`
- [ ] `it calls the next handler when lookup misses`
- [ ] `it stores the response when the response is cacheable`
- [ ] `it does not store responses that are not cacheable`
- [ ] `it builds a CachePolicy from the Cacheable attribute ttl and tags before storing`
- [ ] `it returns the Response value returned by store (not the original) so proxy drivers can decorate headers`

## Acceptance Criteria
- `src/Middleware/PageCacheMiddleware.php` is a `readonly class` implementing `MiddlewareInterface`
- All requirements above pass via unit tests in `tests/Unit/Middleware/PageCacheMiddlewareTest.php`
- Tests use a fake `PageCacheInterface` (anonymous class implementation) — no reflection-based mocking
- The middleware delegates cacheability decisions entirely to `CacheabilityChecker`
- The middleware MUST return the `Response` returned by `PageCache::store(...)` (rather than the original `$response`) so reverse-proxy drivers can decorate headers. Test this with a fake `PageCacheInterface` whose `store()` returns a `Response` with an extra header.
- Strict types declared
- All `@throws` documented

## Implementation Notes
(Left blank — filled in by programmer during implementation)
