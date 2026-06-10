# Task 013: F6 — HMAC-signed envelope for cache-redis payloads

**Status**: pending
**Depends on**: [005]
**Retry count**: 0

## Description
Close PHP object injection over the network-reachable Redis cache. `RedisCacheDriver` calls bare `unserialize()` on values fetched from Redis (~37 in `get()`, ~120 in `getItem()`), so anyone who can write to Redis can inject objects. Wrap the serialized value in an HMAC-SHA256 envelope: on `set()`, store `hmac.serialized`; on read, verify the HMAC with `hash_equals()` BEFORE `unserialize()` and reject loudly (treat as a miss or throw a cache exception) on mismatch. Sign with the app key from `marko/encryption` config (`encryption.key`); throw loudly if the key is empty (no unsigned fallback).

## Context
- Related files:
  - `packages/cache-redis/src/Driver/RedisCacheDriver.php` (`get()` unserialize ~37; `set()` serialize ~52; `getItem()` unserialize ~120)
  - `packages/cache-redis/composer.json` (add `marko/encryption` to `require`)
  - `packages/cache-redis/module.php` (inject the key/config dependency)
  - `packages/cache/src/Exceptions/CacheException.php` (reuse or add a signature-mismatch factory)
  - `packages/cache-redis/tests/`
- File-ownership note: task 005 also edits `RedisCacheDriver.php` (adds `increment()`). This task depends on 005 and runs AFTER it to avoid a parallel edit conflict.
- Patterns to follow:
  - HMAC scheme (locked, identical to task 014): `hash_hmac('sha256', $serialized, $appKey)`, envelope `hex_hmac . '.' . $serialized`, verify with `hash_equals()`. The HMAC is over the RAW `serialize($value)` bytes; on read, split on the FIRST `.` (the hmac hex is fixed-length 64 chars for sha256 — split on the first 64 chars + `.` to avoid ambiguity since the serialized payload itself contains `.`). Document the exact framing so task 014 matches byte-for-byte.
  - App key via `marko/encryption` `EncryptionConfig::key()` (config key `encryption.key`). NOTE: `encryption.php` defaults the key to `''` when `ENCRYPTION_KEY` is unset, so `EncryptionConfig::key()` returns `''` (it does NOT throw). The signer MUST explicitly check for an empty key and throw a loud exception (no unsigned fallback). Constant-time compare via `hash_equals()` only.
  - Acyclic dependency: `marko/encryption` depends only on core/config, so cache-redis → encryption is safe.
  - Build a thin local signer within the package to respect boundaries; inject `EncryptionConfig` (or a `ConfigRepositoryInterface`) into the driver/signer via `module.php`. The driver is container-managed so injection works (unlike the queue `Job`).
  - `increment()` (added in task 005) stores a plain integer and reads it back as an int — it must NOT go through the HMAC envelope (it uses Redis `INCR`, not serialize). Keep `increment()` outside the envelope path; only `set()`/`get()`/`getItem()` serialize values.

## Requirements (Test Descriptions)
- [ ] `it stores an HMAC-signed envelope when setting a redis cache value`
- [ ] `it returns the original value when the stored envelope HMAC verifies`
- [ ] `it rejects a redis value whose HMAC does not verify before unserializing it`
- [ ] `it does not unserialize a redis value that has been tampered with`
- [ ] `it throws loudly when the signing key is empty`
- [ ] `it rejects a stored value that has no envelope framing (legacy/unsigned data)`
- [ ] `it does not route increment() integer counters through the HMAC envelope`
- [ ] `it round-trips a legitimate value through set and get`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
