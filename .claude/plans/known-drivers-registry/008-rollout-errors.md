# Task 008: Roll out known-drivers pattern — marko/errors

**Status**: pending
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/errors`. Two drivers: `errors-simple`, `errors-advanced`. Both bind `ErrorHandlerInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Errors\ErrorHandlerInterface`
- Drivers: `marko/errors-simple`, `marko/errors-advanced`
- Recommended-first ordering: `errors-simple` (prod-safe default — minimal info exposed; errors-advanced is for development with detailed stack traces)

**Description text for known-drivers.php:**
- `marko/errors-simple` → `'Simple error handler (recommended for production — minimal information disclosure)'`
- `marko/errors-advanced` → `'Advanced error handler with pretty stack traces and suggestions (recommended for development)'`

## Sub-steps
1. Create `packages/errors/known-drivers.php`
2. Refactor `packages/errors/src/Exceptions/NoDriverException.php` to read from known-drivers.php. Update existing `packages/errors/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/errors/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/errors/composer.json` `require-dev`; add it (`"marko/testing": "self.version"`) if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both errors drivers`
- [ ] `it lists marko/errors-simple first as the recommended driver`
- [ ] `errors NoDriverException reads from known-drivers.php and includes docs URLs`

## Acceptance Criteria
- `packages/errors/known-drivers.php` exists
- `NoDriverException` refactored
- Validation test passes
- Existing errors tests still pass
- Code follows code standards
