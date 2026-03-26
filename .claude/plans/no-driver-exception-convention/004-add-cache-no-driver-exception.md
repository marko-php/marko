# Task 004: Add NoDriverException to Cache Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the cache package following the standard pattern.

## Context
- Related files:
  - `packages/cache/src/Exceptions/CacheException.php` (extends `Exception`)
  - `packages/cache/src/Exceptions/InvalidKeyException.php`
- Base exception: `CacheException` extends `Exception` (not `MarkoException`), so `NoDriverException` should extend `MarkoException` directly to get structured error format
- Driver packages: `marko/cache-array`, `marko/cache-file`, `marko/cache-redis`
- No module.php exists for this package

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/cache-array, marko/cache-file, and marko/cache-redis`
- [ ] `it provides suggestion with composer require commands for all driver packages`
- [ ] `it includes context about resolving cache interfaces`
- [ ] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/cache/src/Exceptions/NoDriverException.php`.
