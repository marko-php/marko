# Task 013: Add NoDriverException to PubSub Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the pubsub package following the standard pattern.

## Context
- Related files:
  - `packages/pubsub/src/Exceptions/PubSubException.php` (extends `MarkoException`)
- Base exception: `PubSubException` extends `MarkoException` — use `PubSubException`
- Driver packages: `marko/pubsub-pgsql`, `marko/pubsub-redis`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/pubsub-pgsql and marko/pubsub-redis`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving pub/sub interfaces`
- [x] `it extends PubSubException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/pubsub/src/Exceptions/NoDriverException.php`.
