# Task 022: Roll out known-drivers pattern — marko/translation (single-driver)

**Status**: pending
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/translation`. Single driver: `translation-file`.

## Context
- Driver: `marko/translation-file`

**Description text for known-drivers.php:**
- `marko/translation-file` → `'File-based translation driver (PHP array files per locale)'`

## Sub-steps
1. Create `packages/translation/known-drivers.php`
2. Refactor `packages/translation/src/Exceptions/NoDriverException.php`. Update existing `packages/translation/tests/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/translation/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/translation/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/translation-file`
- [ ] `translation NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes`

## Acceptance Criteria
- `packages/translation/known-drivers.php` exists
- `NoDriverException` refactored
- Validation test passes
- Existing translation tests still pass
- Code follows code standards
