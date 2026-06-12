# Task 006: F4 — Batch Redis multi-key ops (MGET / pipeline / variadic DEL)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`RedisCacheDriver::getMultiple`, `setMultiple`, and `deleteMultiple` each loop the single-key `get`/`set`/`delete` methods, producing N Redis round-trips for N keys. Predis supports MGET for batched reads, command pipelining for batched TTL writes, and a variadic DEL. Replace the loops with one MGET, one pipeline, and one variadic DEL respectively, preserving all current multi-key semantics — **including Tier 1's HMAC value signing**.

## Description (CRITICAL — Tier 1 HMAC signer must be preserved)
The CURRENT driver wraps every stored value through `CacheValueSigner` (Tier 1, security feature):
- `set()` stores `$this->cacheValueSigner->wrap(serialize($value))` (envelope format `<64-hex-hmac>.<serialized>`).
- `get()` returns `unserialize($this->cacheValueSigner->verifyAndUnwrap($data))`.

The batch paths MUST go through the SAME signer — NOT raw `serialize`/`unserialize`:
- `setMultiple` MUST `$this->cacheValueSigner->wrap(serialize($value))` for each pair before queueing it in the pipeline.
- `getMultiple` MUST `unserialize($this->cacheValueSigner->verifyAndUnwrap($data))` for each non-null MGET result, applying `$default` for nulls.

If you bypass the signer: (1) the EXISTING tests `sets multiple keys` and `gets multiple keys` FAIL, because a `setMultiple(...)` followed by `get('key1')` round-trips through `get()` → `verifyAndUnwrap()` and throws `TamperedCacheValueException` on an unsigned value; and (2) you silently strip tamper protection from the multi-key path. `increment()` (Tier 1, lines 172-188) is NOT routed through the signer — do NOT touch it and do NOT route batch values around the signer "to match increment".

## Context
- Related files:
  - `packages/cache-redis/src/Driver/RedisCacheDriver.php` (`getMultiple` ~129-140, `setMultiple` ~145-154, `deleteMultiple` ~159-167; single-key `get`/`set` for the `CacheValueSigner` wrap/verify, prefixing, TTL, and `validateKey` reference)
  - `packages/cache-redis/src/Signer/CacheValueSigner.php` (`wrap(string): string` / `verifyAndUnwrap(string): string`)
  - `packages/cache-redis/tests/Unit/RedisCacheDriverTest.php` (`MockRedisClient extends Predis\Client` with public `$storage`/`$ttls` at line 19; behavioral expectations for getMultiple/setMultiple/deleteMultiple at lines 351-398 already exist — keep them green and add batching assertions)
- Patterns to follow:
  - `getMultiple`: `validateKey` + prefix all keys, one `mget(...$prefixedKeys)`, then map back in input-key order — for each non-null hit `unserialize($this->cacheValueSigner->verifyAndUnwrap($value))`, use `$default` for nulls.
  - `setMultiple`: open a Predis `pipeline`, queue `setex($prefixedKey, $ttl, $this->cacheValueSigner->wrap(serialize($value)))` (TTL>0, default TTL applies when `$ttl` is null) or `set($prefixedKey, $envelope)` (TTL 0) per pair, execute once; return `true`. `validateKey` every key first.
  - `deleteMultiple`: one `del(...$prefixedKeys)`; return `true`. (The existing `MockRedisClient::del` at lines 71-93 is ALREADY variadic and flattens array args — do not change its signature; just call it variadically from the driver.)
  - Extend `MockRedisClient` to add `mget(...$keys): array` (values in argument order, null for misses, reading `$storage`) and a `pipeline(callable $callback): array` that invokes the callback with a recorder collecting queued `setex`/`set` commands and applies them to `$storage`/`$ttls`. Record invocation counts so tests can assert exactly one MGET / one pipeline execution / one DEL.
  - `@throws` on `getMultiple`/`setMultiple` must include `TamperedCacheValueException` (matching `get`/`set`); `InvalidKeyException` on all three.
  - Independent of any Tier 1 `CacheInterface::increment`; do not touch it.

## Requirements (Test Descriptions)
- [ ] `it returns values for all requested keys via getMultiple`
- [ ] `it returns the default for missing keys in getMultiple`
- [ ] `it preserves input key order in the getMultiple result`
- [ ] `it issues a single MGET for getMultiple instead of one get per key`
- [ ] `it verifies the HMAC envelope on each value read by getMultiple`
- [ ] `it stores all pairs via setMultiple with the given TTL`
- [ ] `it applies the default TTL in setMultiple when none is given`
- [ ] `it stores persistent pairs without a TTL when setMultiple TTL is zero`
- [ ] `it issues the setMultiple writes in a single pipeline`
- [ ] `it stores each setMultiple value as a signed HMAC envelope`
- [ ] `it round-trips values written by setMultiple back through getMultiple`
- [ ] `it deletes all given keys via deleteMultiple`
- [ ] `it issues a single variadic DEL for deleteMultiple`
- [ ] `it validates every key in the multi-key operations`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
