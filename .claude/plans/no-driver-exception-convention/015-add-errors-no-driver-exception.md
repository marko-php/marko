# Task 015: Add NoDriverException to Errors Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the errors package following the standard pattern. This package currently has no `Exceptions/` directory, so it needs to be created.

## Context
- Related files:
  - `packages/errors/src/Contracts/ErrorHandlerInterface.php`
- No existing exception classes in this package
- Base exception: extend `MarkoException` directly
- Driver packages: `marko/errors-advanced`, `marko/errors-simple`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/errors-advanced and marko/errors-simple`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving error handler interfaces`
- [x] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern
- `Exceptions/` directory created in the errors package

## Implementation Notes
Create new directory `packages/errors/src/Exceptions/` and new file `NoDriverException.php`. May need to verify PSR-4 autoload config in `packages/errors/composer.json`.
