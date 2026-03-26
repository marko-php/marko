# Task 018: Add NoDriverException to Admin Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the admin package following the standard pattern.

## Context
- Related files:
  - `packages/admin/src/Exceptions/AdminException.php` (extends `MarkoException`)
- Base exception: `AdminException` extends `MarkoException` — use `AdminException`
- Driver packages: `marko/admin-api`, `marko/admin-auth`, `marko/admin-panel`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/admin-api, marko/admin-auth, and marko/admin-panel`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving admin interfaces`
- [x] `it extends AdminException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/admin/src/Exceptions/NoDriverException.php`.
