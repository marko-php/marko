# Task 006: F4 — Batch Redis multi-key ops (MGET / pipeline / variadic DEL)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`RedisCacheDriver::getMultiple`, `setMultiple`, and `deleteMultiple` each loop the single-key `get`/`set`/`delete` methods, producing N Redis round-trips for N keys. Predis supports MGET for batched reads, command pipelining for batched TTL writes, and a variadic DEL. Replace the loops with one MGET, one pipeline, and one variadic DEL respectively, preserving all current multi-key semantics.

## Context
- Related files:
  - `packages/cache-redis/src/Driver/RedisCacheDriver.php` (`getMultiple`/`setMultiple`/`deleteMultiple` ~132-160; single-key `get`/`set`/`delete` for serialization, prefixing, TTL, and `validateKey` reference)
  - `packages/cache-redis/tests/Unit/...RedisCacheDriverTest.php` (`MockRedisClient extends Predis\Client` with public `$storage`/`$ttls`; behavioral expectations for getMultiple/setMultiple/deleteMultiple already exist — keep them green and add batching assertions)
- Patterns to follow:
  - `getMultiple`: prefix + `validateKey` all keys, one `mget(...$prefixedKeys)`, then map back in input-key order — unserialize hits, use `$default` for nulls.
  - `setMultiple`: open a Predis `pipeline`, queue `setex` (TTL>0, default TTL applies when `$ttl` is null) or `set` (TTL 0) per pair, execute once; return `true`.
  - `deleteMultiple`: one `del(...$prefixedKeys)`; return `true`.
  - Extend `MockRedisClient` to implement `mget`, a `pipeline` callback collecting queued commands, and a variadic `del`, recording invocation counts so tests can assert a single batched call.
  - Independent of any Tier 1 `CacheInterface::increment`; do not touch it.

## Requirements (Test Descriptions)
- [ ] `it returns values for all requested keys via getMultiple`
- [ ] `it returns the default for missing keys in getMultiple`
- [ ] `it preserves input key order in the getMultiple result`
- [ ] `it issues a single MGET for getMultiple instead of one get per key`
- [ ] `it stores all pairs via setMultiple with the given TTL`
- [ ] `it applies the default TTL in setMultiple when none is given`
- [ ] `it stores persistent pairs without a TTL when setMultiple TTL is zero`
- [ ] `it issues the setMultiple writes in a single pipeline`
- [ ] `it deletes all given keys via deleteMultiple`
- [ ] `it issues a single variadic DEL for deleteMultiple`
- [ ] `it validates every key in the multi-key operations`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
