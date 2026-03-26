# Task 002: Update View Package NoDriverException

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Update the existing `NoDriverException` in the view package to follow the standardized pattern with a flat `DRIVER_PACKAGES` constant.

## Context
- Related files:
  - `packages/view/src/Exceptions/NoDriverException.php` (already exists, needs updating)
  - `packages/view/tests/Exceptions/NoDriverExceptionTest.php`
  - `packages/view/tests/Feature/IntegrationTest.php` (references NoDriverException)
- Currently has hardcoded suggestion string, needs `DRIVER_PACKAGES` constant
- Base exception: `ViewException` (extends `MarkoException`) — correct, keep it

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/view-latte`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving ViewInterface`
- [x] `it extends ViewException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern
- Existing tests updated to match new format

## Implementation Notes
Also check and update `docs/src/content/docs/packages/view.md` if it references `NoDriverException` — the exception message/format may have changed.
