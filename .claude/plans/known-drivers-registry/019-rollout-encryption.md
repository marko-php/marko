# Task 019: Roll out known-drivers pattern — marko/encryption (single-driver)

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/encryption`. Single driver: `encryption-openssl`.

## Context
- Driver: `marko/encryption-openssl`
- No siblings; no conflict declaration needed

**Description text for known-drivers.php:**
- `marko/encryption-openssl` → `'OpenSSL-based symmetric encryption driver (AES-256-GCM)'`

## Sub-steps
1. Create `packages/encryption/known-drivers.php`
2. Refactor `packages/encryption/src/Exceptions/NoDriverException.php`. Update existing `packages/encryption/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/encryption/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/encryption/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/encryption-openssl`
- [ ] `encryption NoDriverException reads from known-drivers.php and includes docs URL`
- [ ] `validation test passes (vacuous conflict assertion with one driver)`

## Acceptance Criteria
- `packages/encryption/known-drivers.php` exists with the single entry
- `NoDriverException` refactored
- Validation test passes
- Existing encryption tests still pass
- Code follows code standards
