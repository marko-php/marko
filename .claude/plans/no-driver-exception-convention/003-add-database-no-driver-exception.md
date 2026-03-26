# Task 003: Add NoDriverException to Database Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the database package following the standard pattern. The existing `DatabaseException::noDriverInstalled(string $driver)` method serves a different purpose (specific driver errors) and can be kept, but the new class handles the "no driver at all" case.

## Context
- Related files:
  - `packages/database/src/Exceptions/DatabaseException.php` (has `DRIVER_PACKAGES` and `noDriverInstalled($driver)`)
  - `packages/database/tests/DatabaseExceptionTest.php`
  - `packages/database/tests/Feature/DriverErrorHandlingTest.php`
- Base exception: `MarkoException` (database has no package-level base exception)
- Driver packages: `marko/database-mysql`, `marko/database-pgsql`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/database-mysql and marko/database-pgsql`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving database interfaces`
- [x] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern
- `DatabaseException::noDriverInstalled(string $driver)` remains for specific driver errors

## Implementation Notes
Create new file at `packages/database/src/Exceptions/NoDriverException.php`.
