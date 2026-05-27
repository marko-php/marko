# Task 018: Roll out known-drivers pattern — marko/authentication (single-driver)

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/authentication`. Single driver: `authentication-token`. No siblings yet; the validation test still runs but the conflict-block assertion is vacuous (no other drivers to check against). When a second driver lands, the assertion automatically gains real teeth — no code changes needed.

## Context
- Driver: `marko/authentication-token`
- Recommended-first: only one driver, so listed alone
- No mutual `conflict` declaration needed yet (no siblings)
- `marko/authentication`'s `NoDriverException` already exists — just needs refactoring

**Description text for known-drivers.php:**
- `marko/authentication-token` → `'Token-based authentication driver (signed token sessions)'`

## Sub-steps
1. Create `packages/authentication/known-drivers.php` with the single entry
2. Refactor `packages/authentication/src/Exceptions/NoDriverException.php`. Update existing `packages/authentication/tests/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/authentication/tests/KnownDriversValidationTest.php` (conflict assertion is vacuously satisfied)
4. Verify `marko/testing` is in `packages/authentication/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/authentication-token`
- [ ] `authentication NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes (vacuous conflict assertion with one driver)`

## Acceptance Criteria
- `packages/authentication/known-drivers.php` exists with the single entry
- `NoDriverException` refactored
- Validation test passes (no conflict declaration needed on the lone driver)
- Existing authentication tests still pass
- Code follows code standards
