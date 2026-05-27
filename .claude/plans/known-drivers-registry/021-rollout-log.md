# Task 021: Roll out known-drivers pattern — marko/log (single-driver)

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/log`. Single driver: `log-file`.

## Context
- Driver: `marko/log-file`

**Description text for known-drivers.php:**
- `marko/log-file` → `'File-based logger with rotation'`

## Sub-steps
1. Create `packages/log/known-drivers.php`
2. Refactor `packages/log/src/Exceptions/NoDriverException.php`. Update existing `packages/log/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/log/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/log/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/log-file`
- [ ] `log NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes`

## Acceptance Criteria
- `packages/log/known-drivers.php` exists with the single entry
- `NoDriverException` refactored
- Validation test passes
- Existing log tests still pass
- Code follows code standards
