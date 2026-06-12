# Task 012: F6 — Harden unserialize() in file cache backends (allowed_classes=false for page-cache; object-preserving for cache-file)

**Status**: complete
**Depends on**: [005]
**Retry count**: 0

## Description
Harden the local file cache backends' bare `unserialize()` calls. The two backends differ in what they store:
- **`page-cache-file`** stores ONLY arrays of scalars/strings (page payload: status_code/body/headers/expires_at; hash index: arrays of string hashes). For these, pass `unserialize($content, ['allowed_classes' => false])` so a tampered file cannot instantiate arbitrary classes. This is the locked approach for page-cache.
- **`cache-file`** (`FileCacheDriver`) stores an arbitrary `mixed` value under the `value` key. Consumers LEGITIMATELY cache objects — the existing test `it('sets and gets object value')` stores a `stdClass` and asserts equality on read-back. `allowed_classes => false` here would turn that object into `__PHP_Incomplete_Class` and BREAK the existing test. The trust boundary for the file cache is the local filesystem (an attacker who can write cache files already has the box). The locked Tier 1 decision is to KEEP object support in `cache-file` (do NOT pass `allowed_classes => false` on the value), and rely on the existing shape validation (`is_array($data) && array_key_exists('value', ...)`) to reject a non-envelope payload. Do NOT weaken the existing miss-on-corrupt behavior. (If a stricter posture is later wanted, wrap the file cache value in the HMAC envelope like cache-redis — that is explicitly out of Tier 1 scope.)

Net effect: `page-cache-file` gets `allowed_classes => false` at all three `unserialize` sites; `cache-file` keeps its current `unserialize($content)` for the value but its shape-validation stays/loud-misses on garbage. Verify the existing `cache-file` object round-trip test still passes.

## Context
- Related files:
  - `packages/cache-file/src/Driver/FileCacheDriver.php` (`unserialize($content)` ~253; the result is validated as an array with `value`/`created_at` keys ~255)
  - `packages/page-cache-file/src/Driver/FilePageCacheDriver.php` (`unserialize` at ~42, ~132, ~249 — page payload + hash index)
  - tests under `packages/cache-file/tests/` and `packages/page-cache-file/tests/`
- File-ownership note: task 005 also edits `FileCacheDriver.php` (adds `increment()`). This task depends on 005 and runs AFTER it to avoid a parallel edit conflict on the same file.
- Patterns to follow:
  - Local-file backends (trust boundary = filesystem). `allowed_classes => false` is the locked approach ONLY for `page-cache-file` (no objects stored). `cache-file` keeps object support (see Description).
  - Preserve existing miss-on-corrupt behavior; do not weaken the existing shape validation.
  - HMAC is reserved for network brokers (tasks 013/014).

## Requirements (Test Descriptions)
- [x] `it treats a file cache entry that decodes to an unexpected shape as a miss`
- [x] `it still round-trips a legitimately stored array value through the file cache`
- [x] `it still round-trips a legitimately stored object value through the file cache (object support preserved)`
- [x] `it does not instantiate a disallowed class when decoding a tampered page-cache payload`
- [x] `it does not instantiate a disallowed class when decoding a tampered page-cache hash index`
- [x] `it still round-trips a legitimately stored page-cache entry`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

### page-cache-file (`FilePageCacheDriver`)
- `lookup()`: Changed `unserialize($content)` to `unserialize($content, ['allowed_classes' => false])`. Added type guards (`is_int`, `is_string`, `is_array`) so that `__PHP_Incomplete_Class` objects produced by `allowed_classes => false` cause a shape-validation miss (returns `null`) rather than a `TypeError` when constructing `Response`.
- `purgeTag()`: Changed tag-index `unserialize($content)` to `unserialize($content, ['allowed_classes' => false])`. Extracted result into `$decoded`, with `is_array($decoded)` guard. Added `is_string($hash)` check inside the `foreach` loop to skip any non-string entries (e.g., `__PHP_Incomplete_Class` values from a tampered file).
- `appendToTagIndex()`: Same treatment as `purgeTag()` — `allowed_classes => false` + `is_array` guard.

### cache-file (`FileCacheDriver`)
- No changes to `unserialize()` calls. The general cache legitimately stores objects (`stdClass` etc.), and the trust boundary is the local filesystem. Shape validation (`is_array && array_key_exists('value') && isset('created_at')`) already rejects malformed payloads (returns `null` / miss). Object support preserved — existing `it('sets and gets object value')` test continues to pass.

### Tests added (6 total)
- `packages/cache-file/tests/Unit/FileCacheDriverTest.php`: 3 new tests (shape-miss, array round-trip, object round-trip) — all passed immediately, confirming existing behavior.
- `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTest.php`: 2 new tests (page-cache round-trip, payload tamper guard).
- `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTagsTest.php`: 1 new test (hash-index tamper guard).
