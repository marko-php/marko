# Task 003: RedisCacheDriver Core CRUD Operations

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement RedisCacheDriver with core CRUD operations: get, set, has, delete, clear. Includes key validation, PHP serialization, TTL support, and key prefixing. This is the main driver class implementing CacheInterface.

## Context
- Related files:
  - `packages/cache-array/src/Driver/ArrayCacheDriver.php` (implementation pattern)
  - `packages/cache-file/src/Driver/FileCacheDriver.php` (implementation pattern)
  - `packages/cache/src/Contracts/CacheInterface.php` (interface to implement)
  - `packages/cache/src/Exceptions/InvalidKeyException.php` (key validation)
  - `packages/cache/src/Config/CacheConfig.php` (for defaultTtl)
  - `packages/cache-array/tests/Unit/ArrayCacheDriverTest.php` (test pattern)
- Patterns to follow:
  - validateKey() private method using InvalidKeyException (same as array/file)
  - CacheConfig injected for defaultTtl()
  - RedisConnection injected for Redis operations
  - serialize()/unserialize() for values (like cache-file)
  - Key prefixing: `$this->connection->prefix . $key`
  - TTL: null → config default, 0 → persistent (no TTL), >0 → SETEX

## Requirements (Test Descriptions)
- [x] `it implements CacheInterface`
- [x] `it returns default for missing key`
- [x] `it returns custom default for missing key`
- [x] `it sets and gets string value`
- [x] `it sets and gets integer value`
- [x] `it sets and gets array value`
- [x] `it sets and gets null value`
- [x] `it returns true when setting value`
- [x] `it returns true for existing key`
- [x] `it returns false for missing key`
- [x] `it deletes existing key`
- [x] `it returns true when deleting existing key`
- [x] `it returns true when deleting missing key`
- [x] `it clears all prefixed keys`
- [x] `it returns true when clearing`
- [x] `it sets value with TTL`
- [x] `it sets persistent value with zero TTL`
- [x] `it uses default TTL when not specified`
- [x] `it throws exception for empty key`
- [x] `it throws exception for key with invalid characters`
- [x] `it overwrites existing value`
- [x] `it prefixes keys in Redis`

## Acceptance Criteria
- All requirements have passing tests
- All tests use mocked Predis client (no real Redis)
- Key validation matches array/file driver behavior exactly
- TTL semantics match array/file driver behavior exactly
- Code follows all Marko code standards

## Implementation Notes
- Fully rewrote `RedisCacheDriver` with constructor accepting `RedisConnection` and `CacheConfig`
- All CRUD operations delegate to Predis client via `$this->connection->client()`
- Values are serialized with `serialize()` before storing, `unserialize()` on retrieval
- Keys are prefixed using `$this->connection->prefix` (default: `marko:cache:`)
- TTL: null uses `$this->config->defaultTtl()`, 0 means persistent (no expiry), >0 uses `setex`
- Key validation reuses `InvalidKeyException` exactly like ArrayCacheDriver
- `clear()` uses `keys(prefix*)` then `del(...keys)` to only remove prefixed keys
- `delete()` always returns true (same as ArrayCacheDriver pattern)
- Test uses named `MockRedisClient` class extending `Predis\Client` with in-memory storage
