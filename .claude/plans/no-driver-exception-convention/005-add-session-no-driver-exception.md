# Task 005: Add NoDriverException to Session Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the session package following the standard pattern.

## Context
- Related files:
  - `packages/session/src/Exceptions/SessionException.php` (extends `Exception`)
- Base exception: `SessionException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/session-database`, `marko/session-file`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/session-database and marko/session-file`
- [ ] `it provides suggestion with composer require commands for all driver packages`
- [ ] `it includes context about resolving session interfaces`
- [ ] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/session/src/Exceptions/NoDriverException.php`.
