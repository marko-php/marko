# Task 024: Roll out known-drivers pattern — marko/page-cache (single-driver; entity is add-on)

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the single-driver variant to `marko/page-cache`. The page-cache family has TWO sibling packages on disk (`marko/page-cache-file`, `marko/page-cache-entity`) but ONLY ONE is a driver — `page-cache-file` binds `PageCacheInterface`; `page-cache-entity` has empty `bindings: []` and provides cache-invalidation observers via `#[Observer]` attributes. The entity package is an ADD-ON, not a driver.

## Context
- Driver: `marko/page-cache-file`
- Add-on (NOT enrolled in known-drivers.php): `marko/page-cache-entity`
- `marko/page-cache-entity` will appear in skeleton's suggest block (added in task 025) but is NOT enrolled in known-drivers.php (add-on, not a driver).

**Description text for known-drivers.php:**
- `marko/page-cache-file` → `'File-based page cache driver'`

## Sub-steps
1. Create `packages/page-cache/known-drivers.php` with ONLY the file driver entry
2. Refactor `packages/page-cache/src/Exceptions/NoDriverException.php`. **Note:** unlike the other 17 NoDriverException classes (which use a `noDriverInstalled()` factory), page-cache's current factory is `noBinding()`. **Rename it to `noDriverInstalled()` for consistency** with the rest of the codebase as part of this refactor. Confirm no callers exist (per plan scope notes, the exception is currently unused — `NoDriverException` factories are not yet wired to any throw site). Update the existing test `packages/page-cache/tests/Unit/Exceptions/NoDriverExceptionTest.php` to use the new method name.
3. Add `packages/page-cache/tests/KnownDriversValidationTest.php`
4. Add `marko/testing` to `packages/page-cache/composer.json` `require-dev` if not already present (verify against current composer.json)

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing only marko/page-cache-file`
- [ ] `it does not list marko/page-cache-entity (add-on, not driver)`
- [ ] `page-cache NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `page-cache NoDriverException exposes a noDriverInstalled() factory (renamed from noBinding for consistency)`
- [ ] `validation test passes`

## Acceptance Criteria
- `packages/page-cache/known-drivers.php` exists with the single entry
- `NoDriverException` refactored
- Validation test passes
- Existing page-cache tests still pass
- Code follows code standards
