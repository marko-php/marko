# Task 005: F2 — Implement atomic increment() in array, file, and redis cache drivers

**Status**: pending
**Depends on**: [004]
**Retry count**: 0

## Description
Implement `increment()` in all three cache drivers so the new `CacheInterface` contract is honored everywhere. Array driver increments in-process; file driver increments under an exclusive `flock()` (read-modify-write while holding the lock) to be atomic across processes; redis driver uses `INCR` and sets `EXPIRE` ONLY when the returned value is 1 (first increment). The first increment of a missing key returns 1 and sets the TTL; subsequent increments do NOT reset the TTL (resetting on every call would make a rate-limit window never close → permanent lockout). Also update the two anonymous `CacheInterface` implementers in `packages/health/tests/Unit/CacheHealthCheckTest.php` (lines ~12 and ~88) to satisfy the new contract, or `marko/health`'s suite will fatal.

## Context
- Related files:
  - `packages/cache-array/src/Driver/ArrayCacheDriver.php`
  - `packages/cache-file/src/Driver/FileCacheDriver.php` (file locking; same atomic-write area as existing `serialize`/`unserialize` at ~253/272)
  - `packages/cache-redis/src/Driver/RedisCacheDriver.php` (uses `$this->connection->client()`)
  - `packages/health/tests/Unit/CacheHealthCheckTest.php` (add `increment()` to both anonymous `CacheInterface` stubs — lines ~12, ~88 — so the contract change does not fatal the health suite)
  - tests under each driver package's `tests/`
- File-ownership note: this task adds `increment()` to `FileCacheDriver.php` and `RedisCacheDriver.php`. Tasks 012 (file allowed_classes) and 013 (redis HMAC) also edit those same files and therefore depend on this task — they run AFTER 005, not in parallel with it.
- Patterns to follow:
  - Existing `set()`/`get()` key-prefixing and TTL handling in each driver.
  - Loud errors via `InvalidKeyException` for invalid keys.
  - Do NOT introduce hidden fallbacks; first-increment semantics (return 1, apply TTL) must be explicit.

## Requirements (Test Descriptions)
- [ ] `it returns 1 when incrementing a key that does not yet exist (array driver)`
- [ ] `it returns the incremented value on a subsequent increment (array driver)`
- [ ] `it returns 1 when incrementing a key that does not yet exist (file driver)`
- [ ] `it returns the incremented value on a subsequent increment (file driver)`
- [ ] `it applies the ttl on the first increment so the counter expires (file driver)`
- [ ] `it returns 1 when incrementing a key that does not yet exist (redis driver)`
- [ ] `it returns the incremented value on a subsequent increment (redis driver)`
- [ ] `it sets an expiry on the key when incrementing (redis driver)`
- [ ] `it does not reset the ttl on a subsequent increment (redis driver)`
- [ ] `it does not reset the ttl on a subsequent increment (file driver)`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
