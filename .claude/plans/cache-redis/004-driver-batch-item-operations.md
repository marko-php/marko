# Task 004: RedisCacheDriver Batch and Item Operations

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Add batch operations (getMultiple, setMultiple, deleteMultiple) and cache item support (getItem) to RedisCacheDriver. Batch operations use Redis pipelines for efficiency.

## Context
- Related files:
  - `packages/cache-redis/src/Driver/RedisCacheDriver.php` (from task 003)
  - `packages/cache/src/CacheItem.php` (CacheItem::hit/miss factory methods)
  - `packages/cache-array/src/Driver/ArrayCacheDriver.php` (batch/item pattern)
  - `packages/cache-array/tests/Unit/ArrayCacheDriverTest.php` (test pattern)
- Patterns to follow:
  - getMultiple/setMultiple/deleteMultiple delegate to single-item methods (like array/file)
  - getItem returns CacheItem::hit() or CacheItem::miss()
  - CacheItem expiresAt uses DateTimeImmutable from Redis TTL

## Requirements (Test Descriptions)
- [x] `it returns cache item for hit`
- [x] `it returns cache item for miss`
- [x] `it returns cache item with expiration`
- [x] `it returns cache item without expiration for persistent key`
- [x] `it gets multiple keys`
- [x] `it gets multiple with custom default`
- [x] `it sets multiple keys`
- [x] `it returns true when setting multiple`
- [x] `it deletes multiple keys`
- [x] `it returns true when deleting multiple`

## Acceptance Criteria
- All requirements have passing tests
- getItem returns proper CacheItem with TTL metadata from Redis
- Batch operations validate all keys
- Full test suite passes (pest --parallel)
- Linting passes (php-cs-fixer fix)
- Code follows all Marko code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
