# Task 015: Roll out known-drivers pattern — marko/session

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/session`. Two drivers: `session-file`, `session-database`. Both bind `SessionHandlerInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Session\Contracts\SessionHandlerInterface` (verified at `packages/session/src/Contracts/SessionHandlerInterface.php`)
- Drivers: `marko/session-file`, `marko/session-database`
- Recommended-first ordering: `session-file` (zero-infrastructure default); session-database for distributed apps or where session data needs to be queryable

**Description text for known-drivers.php:**
- `marko/session-file` → `'File-based session driver (recommended default for single-server apps)'`
- `marko/session-database` → `'Database-backed session driver (recommended for distributed deployments and queryable session data)'`

## Sub-steps
1. Create `packages/session/known-drivers.php`
2. Refactor `packages/session/src/Exceptions/NoDriverException.php`. Update existing `packages/session/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/session/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/session/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both session drivers`
- [ ] `it lists marko/session-file first as the recommended driver`
- [ ] `session NoDriverException reads from known-drivers.php and includes docs URLs`

## Acceptance Criteria
- `packages/session/known-drivers.php` exists
- `NoDriverException` refactored
- Validation test passes
- Existing session tests still pass
- Code follows code standards
