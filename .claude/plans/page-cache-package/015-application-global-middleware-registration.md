# Task 015: Register PageCacheMiddleware in Application::GLOBAL_MIDDLEWARE

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Register `Marko\PageCache\Middleware\PageCacheMiddleware` in the hardcoded `Application::GLOBAL_MIDDLEWARE` constant in `packages/core/src/Application.php`. Without this, `PageCacheMiddleware` is never invoked by `Router` and the entire feature silently does nothing — `marko/page-cache` is not auto-discovered as middleware; the framework's only registration mechanism for global middleware is this constant.

The existing `discoverGlobalMiddleware()` method already calls `class_exists()` on each entry before adding it to the runtime list, so adding the FQCN here is safe even when `marko/page-cache` is not installed.

## Context
- Related files:
  - `packages/core/src/Application.php` — contains `GLOBAL_MIDDLEWARE` const at the top of `discoverRoutes()` and `discoverGlobalMiddleware()`
  - `packages/core/tests/Unit/ApplicationTest.php` — has tests like `it includes SessionMiddleware in global middleware` and `it includes LayoutMiddleware in global middleware` that read the constant via reflection. Add an analogous test for `PageCacheMiddleware`.
- Pattern to follow:
  - Insert the FQCN string into `GLOBAL_MIDDLEWARE` in alphabetical/dependency order. `PageCacheMiddleware` should run **before** `SessionMiddleware` and `LayoutMiddleware` so cache hits short-circuit the full pipeline (cached responses do not need to start sessions or run layout). Place it as the FIRST entry in the array.

## Requirements (Test Descriptions)
- [ ] `it includes PageCacheMiddleware in global middleware`
- [ ] `it lists PageCacheMiddleware before SessionMiddleware in global middleware order`

## Acceptance Criteria
- `Marko\PageCache\Middleware\PageCacheMiddleware` is the first entry in `Application::GLOBAL_MIDDLEWARE`
- New test in `packages/core/tests/Unit/ApplicationTest.php` mirroring the existing SessionMiddleware/LayoutMiddleware tests, verifying:
  - The FQCN appears in the constant
  - It precedes `Marko\Session\Middleware\SessionMiddleware`
- Existing ApplicationTest cases still pass (the `class_exists` guard keeps the entry inert under normal test conditions where `marko/page-cache` is not installed in core's vendor — but in this monorepo it IS available because of the path repo setup, so the entry will be active in integration runs; this is fine)
- Strict types unchanged
- No other modifications to `Application.php`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
