# Task 009: Add NoDriverException to Encryption Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the encryption package following the standard pattern.

## Context
- Related files:
  - `packages/encryption/src/Exceptions/EncryptionException.php` (extends `Exception`)
- Base exception: `EncryptionException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/encryption-openssl`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/encryption-openssl`
- [ ] `it provides suggestion with composer require command`
- [ ] `it includes context about resolving encryption interfaces`
- [ ] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/encryption/src/Exceptions/NoDriverException.php`.
