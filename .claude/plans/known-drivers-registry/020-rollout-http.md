# Task 020: Roll out known-drivers pattern — marko/http (single-driver)

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/http`. Single driver: `http-guzzle`.

## Context
- Driver: `marko/http-guzzle`

**Description text for known-drivers.php:**
- `marko/http-guzzle` → `'Guzzle-based HTTP client driver'`

## Sub-steps
1. Create `packages/http/known-drivers.php`
2. Refactor `packages/http/src/Exceptions/NoDriverException.php`. Update existing `packages/http/tests/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/http/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/http/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/http-guzzle`
- [ ] `http NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes`

## Acceptance Criteria
- `packages/http/known-drivers.php` exists with the single entry
- `NoDriverException` refactored
- Validation test passes
- Existing http tests still pass
- Code follows code standards
