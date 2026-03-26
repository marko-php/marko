# Task 011: Add NoDriverException to Log Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the log package following the standard pattern.

## Context
- Related files:
  - `packages/log/src/Exceptions/LogException.php` (extends `Exception`)
- Base exception: `LogException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/log-file`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/log-file`
- [ ] `it provides suggestion with composer require command`
- [ ] `it includes context about resolving logger interfaces`
- [ ] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/log/src/Exceptions/NoDriverException.php`.
