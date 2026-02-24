# Task 017: Config refactor — hashing, pagination, translation, admin, queue-sync packages

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ConfigRepositoryInterface anonymous stubs in hashing (3 files), pagination, translation (2 instances), admin, and queue-sync test files with FakeConfigRepository. Add `marko/testing` to require-dev for each package.

## Context
- Related files:
  - `packages/hashing/tests/Unit/Config/HashConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/hashing/tests/Unit/Factory/HasherFactoryTest.php` — ConfigRepositoryInterface (line 16)
  - `packages/hashing/tests/Feature/HashingIntegrationTest.php` — ConfigRepositoryInterface (line 25)
  - `packages/pagination/tests/Unit/PaginationConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/translation/tests/TranslationConfigTest.php` — ConfigRepositoryInterface (lines 9, 83 — 2 instances)
  - `packages/admin/tests/Unit/Config/AdminConfigTest.php` — ConfigRepositoryInterface (line 14)
  - `packages/queue-sync/tests/Unit/SyncQueueFactoryTest.php` — ConfigRepositoryInterface (line 28)
  - 5 composer.json files to update (hashing, pagination, translation, admin, queue-sync)

### Translation Note
Translation's ConfigRepositoryInterface uses `match()` with explicit key mapping and throws `RuntimeException` (not `ConfigNotFoundException`) for missing keys. FakeConfigRepository throws `ConfigNotFoundException`. Verify test expectations handle this correctly — may need to adjust expected exception type.

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository in HashConfigTest`
- [ ] `it uses FakeConfigRepository in HasherFactoryTest`
- [ ] `it uses FakeConfigRepository in HashingIntegrationTest`
- [ ] `it uses FakeConfigRepository in PaginationConfigTest`
- [ ] `it uses FakeConfigRepository in TranslationConfigTest (2 instances)`
- [ ] `it uses FakeConfigRepository in AdminConfigTest`
- [ ] `it uses FakeConfigRepository in SyncQueueFactoryTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing package tests pass unchanged
- Config stubs removed from all 8 files
- `marko/testing` added to require-dev in all 5 composer.json files
- Run: `./vendor/bin/pest packages/hashing/tests/ packages/pagination/tests/ packages/translation/tests/ packages/admin/tests/ packages/queue-sync/tests/ --parallel`
