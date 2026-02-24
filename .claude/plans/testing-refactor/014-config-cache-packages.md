# Task 014: Config refactor — cache packages

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ConfigRepositoryInterface anonymous stubs in cache, cache-array, cache-file, and cache-redis test files with FakeConfigRepository. Add `marko/testing` to require-dev for each package.

## Context
- Related files:
  - `packages/cache/tests/Unit/CacheConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/cache-array/tests/Unit/ArrayCacheDriverTest.php` — ConfigRepositoryInterface (line 16)
  - `packages/cache-file/tests/Unit/FileCacheDriverTest.php` — ConfigRepositoryInterface (line 38)
  - `packages/cache-redis/tests/Unit/RedisCacheDriverTest.php` — ConfigRepositoryInterface (line 124)
  - 4 composer.json files to update

### Common Pattern
All follow the same pattern — readonly anonymous class wrapping config values:
```php
$configRepo = new readonly class ($defaultTtl) implements ConfigRepositoryInterface {
    public function __construct(private readonly int $defaultTtl) {}
    public function get(string $key, ?string $scope = null): mixed { ... }
    // ... 7 more methods
};
```
Replace with: `new FakeConfigRepository(['cache.default_ttl' => 3600, ...])`

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository in CacheConfigTest`
- [ ] `it uses FakeConfigRepository in ArrayCacheDriverTest`
- [ ] `it uses FakeConfigRepository in FileCacheDriverTest`
- [ ] `it uses FakeConfigRepository in RedisCacheDriverTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing cache package tests pass unchanged
- Config stubs removed from all 4 files
- `marko/testing` added to require-dev in all 4 composer.json files
- Run: `./vendor/bin/pest packages/cache/tests/ packages/cache-array/tests/ packages/cache-file/tests/ packages/cache-redis/tests/ --parallel`
