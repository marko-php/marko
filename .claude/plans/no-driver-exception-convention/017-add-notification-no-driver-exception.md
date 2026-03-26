# Task 017: Add NoDriverException to Notification Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the notification package following the standard pattern. The existing `NotificationException` has a hardcoded suggestion — that will be cleaned up in task 019.

## Context
- Related files:
  - `packages/notification/src/Exceptions/NotificationException.php` (extends `MarkoException`, has hardcoded suggestions)
- Base exception: `NotificationException` extends `MarkoException` — use `NotificationException`
- Driver packages: `marko/notification-database`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/notification-database`
- [ ] `it provides suggestion with composer require command`
- [ ] `it includes context about resolving notification interfaces`
- [ ] `it extends NotificationException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/notification/src/Exceptions/NoDriverException.php`.
