# Task 024: debugbar all() full-decode perf + config-mask completeness

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Two code hardening items in `marko/debugbar`:
1. **`DebugbarStorage::all()` fully decodes every dataset just to list summaries.** It globs `*.json`, then for each file calls `get($id)` which `json_decode`s the ENTIRE stored dataset, only to read four fields (`summary`, `stored_at`, `profiler_url`, and `mtime` from `filemtime`). On a busy dev session with many large datasets this fully decodes every dataset file on each list. Store/read a lightweight summary index (or lazy-decode) so `all()` does not fully decode every dataset.
2. **The default `masked` list misses common secret-named keys.** `config/debugbar.php` masks `*.key`, `*.password`, `*.secret`, `*.token`, `*.api_key`, `*.private_key`. The `matches()` logic turns `*` into `.+`, so a pattern like `*.password` only matches a NESTED key (`db.password`) and never a TOP-LEVEL `password`/`secret`/`token`/`dsn` key, and `dsn` is not covered at all. Make the default mask robustly cover common secret-named config keys (`password`, `secret`, `key`, `token`, `dsn`) at any depth, including top-level.

## Description-note
The `all()` change is a dev-time performance hardening (no behavior change to the returned summaries). The mask change is a default-safety hardening so an enabled config collector does not leak secrets that happen to be top-level or named `dsn`. The docs warning about enabling the config collector is handled by the doc-updater pipeline — this task is code-only.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/debugbar/src/Storage/DebugbarStorage.php` (`put()` ~20-41 — writes full `$dataset` JSON via `file_put_contents(..., LOCK_EX)` then `prune()`; `get()` ~46-66 — full `json_decode(..., JSON_THROW_ON_ERROR)`; `all()` ~71-110 — globs `*.json`, calls `$this->get($id)` per file, then reads only `$dataset['summary']`, `$dataset['stored_at']`, `$dataset['profiler_url']`, and `filemtime($file)`, sorts by `mtime` desc)
  - `/Users/markshust/Sites/marko/packages/debugbar/src/Collectors/ConfigCollector.php` (`collect()` ~16-28 reads `debugbar.options.config.masked`; `mask()`/`matches()` ~35-70 — `matches()` builds `'/^'.str_replace('\\*', '.+', preg_quote($pattern,'/')).'$/'` so `*` → `.+` which requires at least one preceding char)
  - `/Users/markshust/Sites/marko/packages/debugbar/config/debugbar.php` (the `masked` default list ~36-42: `*.key`, `*.password`, `*.secret`, `*.token`, `*.api_key`, `*.private_key`)
  - Tests: `/Users/markshust/Sites/marko/packages/debugbar/tests/` (locate the existing `DebugbarStorageTest.php` and `ConfigCollectorTest.php`)
- Verified findings (source-confirmed):
  - `all()` calls `$this->get($id)` (full decode) for every globbed file to assemble a summary list; the only fields used are `summary`, `stored_at`, `profiler_url`, `mtime`.
  - `put()` writes the whole dataset as one JSON file and there is no separate summary/index artifact today.
  - `matches()` maps `*` to `.+` (one-or-more), so `*.password` matches `db.password` but NOT a bare top-level `password`; `dsn` is absent from the default list entirely.
- Patterns to follow:
  - For `all()`: avoid the full per-file decode. Two acceptable shapes (pick the lower-churn one):
    1. **Summary index file:** in `put()`, additionally maintain a small index (e.g. `index.json` mapping id → `{stored_at, profiler_url, summary}`); `all()` reads that single index plus `filemtime`/glob for ordering. Keep `prune()` and the index in sync (prune removes index entries too).
    2. **Lazy/partial decode:** if a summary index is too invasive, have `put()` ALSO write a tiny `{id}.summary.json` next to `{id}.json` containing only the summary fields, and have `all()` read those summary files instead of full datasets. `get($id)` still full-decodes for the profiler detail view (unchanged).
    Whichever is chosen, `all()` must NOT call the full-decode `get()` per file. The returned summary list shape (keys `id`, `stored_at`, `profiler_url`, `summary`, `mtime`, sorted by `mtime` desc) MUST stay identical so the UI is unaffected.
  - For the mask defaults: add patterns that also match top-level keys and `dsn`. Because `*` → `.+` requires a prefix, add BOTH a bare-key form and a nested form for each secret name — e.g. for each of `password`, `secret`, `key`, `token`, `dsn`: include the exact key (`password`) AND the nested wildcard (`*.password`). Add `dsn`/`*.dsn`. Keep the existing `*.api_key`/`*.private_key`. (Alternatively, if a leading-wildcard form like `*password` is supported by `matches()`, prefer the smallest change that makes a top-level `password` mask — but confirm against `matches()`'s `.+` semantics before relying on it.)
  - All defaults live in `config/debugbar.php` (config-file defaults per code standards) — do NOT hardcode mask patterns in `ConfigCollector`. `matches()`/`mask()` logic itself is correct and should stay.

## Requirements (Test Descriptions)
For `DebugbarStorageTest`:
- [ ] `it lists stored datasets without fully decoding every dataset file`
- [ ] `it returns the same summary fields for each listed dataset`
- [ ] `it orders listed datasets by most-recent first`

For `ConfigCollectorTest`:
- [ ] `it masks a top-level password config key`
- [ ] `it masks a top-level secret config key`
- [ ] `it masks a dsn config key`
- [ ] `it still masks a nested password key`
- [ ] `it leaves non-secret config keys visible`

## Acceptance Criteria
- `all()` produces the identical summary list without fully decoding every dataset file (uses an index or summary-only read).
- The default mask redacts common secret-named keys (`password`, `secret`, `key`, `token`, `dsn`) at top level AND nested; non-secret keys remain visible.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
