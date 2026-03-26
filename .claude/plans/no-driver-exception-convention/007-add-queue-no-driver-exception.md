# Task 007: Add NoDriverException to Queue Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the queue package following the standard pattern. The existing `QueueException` has a hardcoded suggestion — that will be cleaned up in task 019.

## Context
- Related files:
  - `packages/queue/src/Exceptions/QueueException.php` (has hardcoded driver suggestions)
  - `packages/queue/tests/QueueExceptionTest.php`
- Base exception: `QueueException` extends `MarkoException`
- Driver packages: `marko/queue-database`, `marko/queue-rabbitmq`, `marko/queue-sync`
- No module.php exists for this package

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/queue-database, marko/queue-rabbitmq, and marko/queue-sync`
- [ ] `it provides suggestion with composer require commands for all driver packages`
- [ ] `it includes context about resolving queue interfaces`
- [ ] `it extends QueueException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/queue/src/Exceptions/NoDriverException.php`.
