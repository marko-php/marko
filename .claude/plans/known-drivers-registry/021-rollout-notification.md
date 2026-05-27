# Task 021: Roll out known-drivers pattern — marko/notification (single-driver)

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/notification`. Single driver: `notification-database`.

## Context
- Driver: `marko/notification-database`

**Description text for known-drivers.php:**
- `marko/notification-database` → `'Database-backed notification driver'`

## Sub-steps
1. Create `packages/notification/known-drivers.php`
2. Refactor `packages/notification/src/Exceptions/NoDriverException.php`. Update existing `packages/notification/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/notification/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/notification/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/notification-database`
- [ ] `notification NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes`

## Acceptance Criteria
- `packages/notification/known-drivers.php` exists
- `NoDriverException` refactored
- Validation test passes
- Existing notification tests still pass
- Code follows code standards
