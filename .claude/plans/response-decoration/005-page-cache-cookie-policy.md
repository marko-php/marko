# Task 005: Page-Cache Refuses Cookie-Bearing Responses

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Make the page cache refuse to store any response carrying cookies. A cached `Set-Cookie` would be replayed to every later visitor, serving one user's session cookie to everybody else — so this is a security boundary, not an optimization.

## Context
- **Primary (required):** `packages/page-cache/src/CacheabilityChecker.php` — `isResponseCacheable()` at line 34. This is already the central cacheability decision and it **already** rejects a response carrying a literal `set-cookie` header (line 40, via the case-insensitive `getHeader()` helper). Extending it to `$response->cookies() !== []` is one condition, benefits every driver, and needs no new package dependency.
- **Secondary (defence in depth):** `packages/page-cache-file/src/Driver/FilePageCacheDriver.php` — `store()` at line 69 refuses to write, `lookup()` at line 27 never hydrates cookies.
- Tests: existing page-cache and page-cache-file test locations.

- The driver serializes `status_code`, `body`, and `headers` and rebuilds a base `Response` on read (line 59). It is a serialize/hydrate round-trip, NOT the middleware rebuild pattern — do not try to convert it to decoration.
- Hydrating a cached entry must never produce cookies, including for cache files written before this change. The stored payload has no `cookies` key, so `lookup()` must not start writing one.

### Do not inject a logger into the driver
The original draft called for a debug log via `LoggerInterface` in `FilePageCacheDriver`. Don't. `packages/page-cache-file/composer.json` requires only core/config/routing/page-cache — adding `marko/log` pulls a new dependency into a driver package, and the constructor change breaks `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTest.php`, which instantiates the driver directly. If observability is wanted, it belongs in `PageCacheMiddleware` (which already has the container) and is a separate, optional change — not a blocker for this task.

### Interaction with task 006 — read before writing the tests
`SessionMiddleware` is registered as **global middleware** by the session drivers (`packages/session-file/module.php:19-21`, same in `session-database`) and those modules declare `'sequence' => ['after' => ['marko/page-cache']]`. `Router::handle()` builds `[...$globalMiddleware, ...$routeMiddleware]` and `MiddlewarePipeline` peels from the front, so `SessionMiddleware` runs **inside** `PageCacheMiddleware` — its return value is what gets handed to `isResponseCacheable()`.

If task 006 attached a session cookie to every response, this task would silently disable the page cache entirely for any app with sessions enabled, and no existing test would notice. Task 006 has been constrained to attach the cookie only when the session ID is new, regenerated, or destroyed — mirroring what `session_start()` does today. The last requirement below is the cross-task regression test for that.

## Requirements (Test Descriptions)
- [x] `it does not cache a response that carries cookies`
- [x] `it still caches a response that carries no cookies`
- [x] `it still refuses to cache a response carrying a literal set-cookie header`
- [x] `it hydrates a cached response with no cookies`
- [x] `it hydrates a cache entry written before cookies existed without error`
- [x] `it still caches a repeat visit response that passed through session middleware without a new session`

## Acceptance Criteria
- All requirements have passing tests
- Existing page-cache and page-cache-file tests still pass
- No new entries added to `packages/page-cache-file/composer.json` or `packages/page-cache/composer.json`
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- `CacheabilityChecker::isResponseCacheable()` gained one new condition (`$response->cookies() !== []`) ahead of the existing literal `set-cookie` header check — one line, no new dependency, benefits every driver.
- `FilePageCacheDriver` needed no production change for the "defence in depth" behavior described in Context: `store()` never serializes `cookies()` (only `status_code`/`body`/`headers`/`tags`/timestamps), and `lookup()` rebuilds a plain `Response` from that payload, which defaults `cookies` to `[]`. Both new driver-level tests (`it hydrates a cached response with no cookies`, `it hydrates a cache entry written before cookies existed without error`) passed immediately against the existing code — this is expected and was verified before moving on, not an oversight.
- The cross-task regression test (`it still caches a repeat visit response that passed through session middleware without a new session`) lives in the new top-level `tests/Integration/PageCacheSessionMiddlewareTest.php`, not inside `packages/page-cache` or `packages/session`, because it exercises both `Marko\Session\Middleware\SessionMiddleware` and `Marko\PageCache\CacheabilityChecker` together and neither package may depend on the other. The root `composer.json` already requires both `marko/page-cache` and `marko/session` as dev dependencies with PSR-4 test autoloading, and an existing `tests/Integration/QueryBuilderRawConsistencyTest.php` established the pattern for this kind of cross-package test. It passed immediately since task 006 (which could regress this) has not run yet — it now stands as a tripwire.
- Full suite (`composer test` equivalent): 6941 passed, 0 failed. Touched files are clean under `php-cs-fixer` and `phpcs`. `phpstan.neon` only analyses `packages/core/src`, so it is out of scope for these files.
