# Task 003: Refactor rate-limiting package tests to use marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace hand-rolled ConfigRepositoryInterface stub in RateLimiterTest with FakeConfigRepository. Add `marko/testing` to require-dev. RateLimiterInterface stubs in RateLimitMiddlewareTest stay (domain-specific).

## Context
- Related files:
  - `packages/rate-limiting/tests/Unit/RateLimiterTest.php` — `createRateLimitCacheConfig()` returning anonymous ConfigRepositoryInterface (~70 lines)
  - `packages/rate-limiting/composer.json` — add marko/testing to require-dev
- Replacement: `new FakeConfigRepository(['rate_limiting.driver' => 'cache', ...])`
- **Do NOT touch**: RateLimitMiddlewareTest.php inline RateLimiterInterface stubs (domain-specific)

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository instead of inline config stub in RateLimiterTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing rate-limiting package tests pass unchanged
- `marko/testing` added to `packages/rate-limiting/composer.json` require-dev
- RateLimitMiddlewareTest.php untouched
- Run: `./vendor/bin/pest packages/rate-limiting/tests/ --parallel`
