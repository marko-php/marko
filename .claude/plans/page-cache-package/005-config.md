# Task 005: PageCacheConfig + config/page-cache.php

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the configuration class `PageCacheConfig` (typed accessor over `ConfigRepositoryInterface`) and the default config file `config/page-cache.php`. No fallback parameters in getter calls — defaults belong in the config file only, per project standards.

## Context
- Related files:
  - `packages/cache/src/Config/CacheConfig.php` (template)
  - `packages/cache/config/cache.php` (template)
  - `.claude/code-standards.md` — Configuration Standards
- Config keys:
  - `page-cache.driver` (string, default `'file'`)
  - `page-cache.path` (string, default `'storage/page-cache'`)
  - `page-cache.default_ttl` (int, default `3600`)
  - `page-cache.cacheable_status_codes` (array<int>, default `[200, 301]`). 404 is intentionally NOT in the default list — applications often generate 404s based on auth state or feature flags, and a cached 404 served to a different user would mask access correctly. Operators can opt in to caching 404s via env var.
  - `page-cache.cacheable_methods` (array<string>, default `['GET', 'HEAD']`)

## Requirements (Test Descriptions)
- [ ] `it returns the configured driver name`
- [ ] `it returns the configured storage path`
- [ ] `it returns the configured default ttl as int`
- [ ] `it returns the configured cacheable status codes as array of ints`
- [ ] `it returns the configured cacheable methods as array of strings`
- [ ] `it propagates ConfigNotFoundException when a key is missing`

## Acceptance Criteria
- `src/Config/PageCacheConfig.php` is a `readonly class` constructed from `ConfigRepositoryInterface`
- Public methods: `driver(): string`, `path(): string`, `defaultTtl(): int`, `cacheableStatusCodes(): array`, `cacheableMethods(): array`
- `cacheableStatusCodes(): array<int>` — uses `$this->config->getArray('page-cache.cacheable_status_codes')` and casts each entry to `int` before returning. The cast is necessary because env var overrides may be parsed as strings.
- `cacheableMethods(): array<string>` — uses `getArray('page-cache.cacheable_methods')` and casts each entry to `string` (also normalize via `strtoupper()` so `get` and `GET` match identically when compared to `Request::method()`).
- No fallback parameters in `getX()` calls (e.g., `$this->config->getString('page-cache.driver')` — not `getString('page-cache.driver', 'file')`)
- Each method has `@throws ConfigNotFoundException`
- `config/page-cache.php` returns the array shown in Context, with env var overrides per project pattern
- Tests in `tests/Unit/Config/PageCacheConfigTest.php`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
