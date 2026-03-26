# Task 016: Add NoDriverException to Authentication Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the authentication package following the standard pattern.

## Context
- Related files:
  - `packages/authentication/src/Exceptions/AuthException.php` (extends `Exception`)
- Base exception: `AuthException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/authentication-token`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/authentication-token`
- [x] `it provides suggestion with composer require command`
- [x] `it includes context about resolving authentication interfaces`
- [x] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/authentication/src/Exceptions/NoDriverException.php`.
