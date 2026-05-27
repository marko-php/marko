# Task 007: Roll out known-drivers pattern — marko/cache

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the pilot pattern (tasks 002–004) to `marko/cache`. Three drivers: `cache-array`, `cache-file`, `cache-redis`. All bind `CacheInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Cache\CacheInterface`
- Drivers: `marko/cache-array`, `marko/cache-file`, `marko/cache-redis`
- Recommended-first ordering: `cache-file` (sensible default for most apps; redis is opt-in for distributed/perf, array is dev/test-only)
- All three drivers already have `module.php` binding `CacheInterface` — confirmed in audit

**Description text for known-drivers.php:**
- `marko/cache-file` → `'File-based cache driver (recommended for single-server apps)'`
- `marko/cache-redis` → `'Redis cache driver (recommended for distributed deployments and high-throughput apps)'`
- `marko/cache-array` → `'In-memory cache driver (request-lifetime only; intended for testing and dev environments)'`

## Sub-steps (each yields one or more requirements)
1. Create `packages/cache/known-drivers.php` with the three entries (file first)
2. Refactor `packages/cache/src/Exceptions/NoDriverException.php` to read from known-drivers.php and include docs URLs (same shape as task 003). Update existing `packages/cache/tests/Exceptions/NoDriverExceptionTest.php` assertions to match the new output format.
3. Add `packages/cache/tests/KnownDriversValidationTest.php` using `KnownDriversValidator`
4. Verify `marko/testing` is in `packages/cache/composer.json` `require-dev`; add it (`"marko/testing": "self.version"`) if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing all three cache drivers`
- [ ] `it lists marko/cache-file first as the recommended driver`
- [ ] `cache NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `validation test skips skeleton parity assertion when skeleton is absent`

## Acceptance Criteria
- `packages/cache/known-drivers.php` exists with three entries
- `NoDriverException` refactored to mirror database/NoDriverException pattern
- Validation test passes
- Existing cache tests still pass
- Code follows code standards
