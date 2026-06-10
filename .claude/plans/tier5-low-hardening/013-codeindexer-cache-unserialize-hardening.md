# Task 013: codeindexer unsafe cache deserialize + silent corruption

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`IndexCache::load()` does `$this->data = unserialize((string) file_get_contents($cachePath))` with no `allowed_classes` restriction, then unconditionally `return true`. On a corrupt or truncated cache file `unserialize()` returns `false`, but `load()` still reports success, so `ensureLoaded()` never rebuilds and every getter reads `false['modules'] ?? []` — silently reporting an EMPTY index with no rebuild and no loud error. Restrict `allowed_classes` to the indexer's own value-object set, and treat a non-array `unserialize()` result as a load failure (return `false`) so `ensureLoaded()` falls through to `build()`.

## Description-note
This violates two core principles: it's a silent failure (an empty index masquerades as a valid one), and the unrestricted `unserialize()` permits object injection if the cache file is tampered with. The fix makes corruption self-healing (rebuild) and locks deserialization to a safe class allowlist.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/codeindexer/src/Cache/IndexCache.php` (`load()` ~102-117 — the `unserialize(...)`; `return true`; `ensureLoaded()` ~123-135; getters like `getModules()` ~178-182 which read `$this->data['modules'] ?? []`)
  - Tests: `/Users/markshust/Sites/marko/packages/codeindexer/tests/` (locate the existing `IndexCacheTest.php` and extend it)
- Verified findings (source-confirmed):
  - `load()` currently: returns `false` if file missing or `isStale()`, otherwise `unserialize(...)` then `return true` with NO check on the result.
  - `build()` writes `serialize($this->data)` where `$this->data` is an array keyed `'modules'`, `'observers'`, `'plugins'`, `'preferences'`, `'commands'`, `'routes'`, etc. The stored payload is a plain associative array of arrays — confirm whether the leaf values are scalars/arrays or value objects before choosing the `allowed_classes` set.
- Patterns to follow:
  - If the serialized payload contains only arrays/scalars, pass `['allowed_classes' => false]` (block ALL object instantiation). If it stores value objects (e.g. an `IndexedObserver`/`IndexedPlugin` DTO), pass `['allowed_classes' => [...explicit list...]]` restricted to exactly those classes. Inspect `build()`/the cached DTOs to decide — do NOT guess; the value-object set must be derived from what `build()` actually stores.
  - After `unserialize(...)`, if the result is not an array, return `false` (do not assign to `$this->data`, do not return `true`). This makes `ensureLoaded()` rebuild. A corrupt cache is recoverable, not fatal — rebuild is the correct loud-but-safe response; no exception is required here because `build()` already succeeds from source. (Do NOT introduce a silent-empty path.)
  - Keep the existing missing-file and `isStale()` early returns unchanged.

## Requirements (Test Descriptions)
- [ ] `it rebuilds the index when the cache file is corrupt instead of reporting an empty index`
- [ ] `it returns false from load when the cache deserializes to a non-array`
- [ ] `it restricts unserialize to the indexer value-object allowlist`
- [ ] `it loads a valid cache file and reports its contents`
- [ ] `it still returns false from load when the cache file is missing`

## Acceptance Criteria
- A corrupt/truncated cache never yields a silently-empty index; it triggers a rebuild (or a loud error), and getters return real data afterward.
- `unserialize()` is called with an `allowed_classes` restriction (either `false` or an explicit class list derived from what `build()` stores) — object injection of arbitrary classes is blocked.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
