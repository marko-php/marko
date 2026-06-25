# Task 001: IndexCache re-validates staleness on read (app/ + modules/; additions, edits, deletions)

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Make `IndexCache` re-check staleness on reads that occur *after* the in-memory data is already populated, so the long-lived `mcp:serve` / `lsp:serve` singleton stops serving a frozen snapshot. Scope the re-check to the directories an agent edits at runtime — `app/*` and `modules/**` — and **skip `vendor/`** (vendor changes only via Composer; new vendor packages surface on the next `indexer:rebuild` / cold-warm, which is the documented path). Extend detection to catch **deletions** by persisting the tracked source-file path set in the cache and comparing it against the current walk. No TTL/clock is needed: the scoped walk is cheap (a few `stat()`s) and a rebuild fires at most once per actual change.

## Context
The bug: `ensureLoaded()` returns early whenever `$this->data !== null`, so `isStale()` only ever runs on the very first read in a process. Long-lived servers hold `IndexCache` as a singleton (`packages/codeindexer/module.php`), freezing the snapshot. `isStale()` already re-scans dirs and compares mtimes, but (a) it walks vendor too (~85 modules — unnecessary on the hot path) and (b) mtime cannot observe a removed file — hence the scoping + path-set comparison.

Why no TTL: with vendor skipped, the re-check walks only `app/*` + `modules/**` (typically dozens of files, single-digit ms, pure `stat()`). Re-checking on every read is fine and is *more* responsive than a memo — there is no window where a fresh write is masked. A rebuild storm cannot happen: the first read after a change rebuilds and `save()` stamps the cache file mtime to "now", so subsequent reads see "not stale" until the next real change. At most one `build()` per change.

Verified against source (do not re-derive):
- `ensureLoaded()` short-circuits at line 146 on `$this->data !== null`. The re-check must be added here: when `$this->data` is populated, call the staleness check and, if stale, rebuild (set `$this->data = null` then `load()`/`build()`, or call `build()` directly). Do NOT early-return unconditionally.
- **Ordering trap:** `load()` (line 102) calls `isStale()` at line 110 *before* `$this->data` is populated (line 132). So `isStale()` cannot read the stored path-set from `$this->data` — it isn't loaded yet. The deletion check must read the previously-persisted path-set **directly from the on-disk payload** (a cheap `unserialize` of just the saved set, or factor the comparison into a helper that `isStale()` calls with the on-disk set). The naive "compare `$this->data` path-set" reads null during `load()` and would never detect deletions or false-positive every load. Pick one approach and state it in the implementation notes.
- The MCP/LSP tools (e.g. `packages/mcp/src/Tools/ListModulesTool.php`, `ListRoutesTool.php`) call getters (`getModules()`, `getRoutes()`) which all funnel through `ensureLoaded()`. There is no explicit `load()`/`build()` warm-up at server startup (`McpServer`/`LspServer` constructors do not touch the cache), so `ensureLoaded()` is the single correct seam — confirmed.
- `build()` (line 48) still walks **everything** via `$this->moduleWalker->walk()` (vendor + modules + app) — vendor stays fully indexed; only the *staleness re-check* skips vendor. The tracked path-set is derived from the same `app`/`modules` subtrees the scoped `isStale()` scans. Store it as a plain `list<string>` (sorted) or a `string` hash under a new payload key inside the `compact(...)` array — a plain scalar/array needs **no** new entry in the `unserialize` allowlist (`load()` lines 114–126). Verify it survives the existing allowlist unchanged.

- Related files:
  - `packages/codeindexer/src/Cache/IndexCache.php` — `ensureLoaded()` (146), `isStale()` (157), `build()` (48), `load()` (102), `save()` (87)
  - `packages/codeindexer/src/Module/ModuleWalker.php` — `walk()` (re-scans dirs live; inject a spy in tests to count walks). Note: vendor is `vendor/*/*`, the part the scoped staleness check must exclude.
  - `packages/codeindexer/tests/Unit/Cache/IndexCacheTest.php` — existing suite to preserve (all 12 tests)
- Patterns to follow: existing tmp-dir fixture style in `IndexCacheTest.php`; constructor property promotion; strict types; loud errors. No new constructor parameter is required (no clock).

## Requirements (Test Descriptions)
- [x] `it reflects a newly added app module on the next read after the data was already loaded` (singleton scenario: one `IndexCache` instance, populate via a getter, add a module under `app/` on disk, call the getter again, expect the new module — without constructing a fresh instance)
- [x] `it reflects a newly added route via getRoutes after the data was already loaded`
- [x] `it reflects an edit to a tracked app/modules source file on the next read after first load`
- [x] `it reflects a deleted app module on the next read by comparing the tracked path set`
- [x] `it does not rebuild when no tracked file under app or modules changed`
- [x] `it does not trigger a rebuild when only a vendor file changes after first load` (vendor is excluded from the staleness re-check)
- [x] `it rebuilds at most once for several successive reads following a single change` (no rebuild storm: count walker/build invocations)
- [x] `it treats a cache payload missing the tracked path set as stale so old caches rebuild once` (write a legacy payload WITHOUT the path-set key, then read — expect a rebuild, not a crash)
- [x] `it persists the tracked path set as a plain array or string that requires no new unserialize allowed class`
- [x] `it preserves first-load semantics by loading a fresh on-disk cache without rescanning` (the existing "loads cache from disk without re-scanning when cache is fresh" test must still pass)

## Acceptance Criteria
- `ensureLoaded()` re-evaluates staleness even when `$this->data` is populated, and rebuilds when stale. The re-check fires on reads *after* the in-memory snapshot exists — this is the core singleton fix; an explicit single-instance test (not a fresh-object reload) is required.
- The staleness re-check walks only `app/*` and `modules/**`; `vendor/` is excluded from the re-check (but remains fully indexed by `build()`).
- A rebuild fires at most once per change across successive reads (no rebuild storm), verified by counting walker/`build()` calls.
- `build()` persists the sorted tracked path-set (or hash) under a plain scalar/array payload key (no new `unserialize` allowed class). The deletion comparison reads the previously-persisted set from the **on-disk payload**, not `$this->data`.
- A legacy cache payload missing the path-set key is treated as stale and rebuilt once, cleanly, without an exception.
- All pre-existing `IndexCacheTest` tests still pass (12 tests). No change to runtime routing/autoloading.
- Code follows code standards; `composer test` green for the package.

## Implementation Notes

### Ordering trap resolution
Chose the **direct on-disk read** approach: `isStale()` calls a new private `loadTrackedPathsFromDisk(string $cachePath): ?array` method that reads and unserializes the cache file independently of `$this->data`. This avoids the null problem — during `load()`, `isStale()` is called before `$this->data` is set, so reading `$this->data['trackedPaths']` would always be null. The on-disk read uses the same allowlist as `load()` and is safe.

### trackedPaths key
`build()` calls a new private `collectTrackedPaths(): list<string>` that recursively walks `app/` and `modules/` directories, collects all file paths, sorts them, and returns a plain `list<string>`. This is stored as `trackedPaths` in the `compact()` payload. Since it is a plain PHP array of strings, no new entry in the `unserialize` allowlist is needed.

### ensureLoaded() change
Added a staleness re-check for the `$this->data !== null` branch: if `isStale()` returns true, call `build()` directly (which writes a fresh cache and updates `$this->data`). Subsequent reads after the rebuild see a fresh cache mtime so `isStale()` returns false — no rebuild storm.

### Vendor exclusion
`isStale()` skips modules whose `$module->path` starts with `{rootPath}/vendor/`. These are only re-indexed on a cold-warm / `indexer:rebuild`. The `collectTrackedPaths()` walk scans only `app/` and `modules/`, so vendor files never enter the tracked-path set either.

### Build count tracking in tests
Tests count `observers()` calls (fired only during `build()`) rather than `walk()` calls (which also fire during `isStale()`) to accurately measure rebuild frequency.
