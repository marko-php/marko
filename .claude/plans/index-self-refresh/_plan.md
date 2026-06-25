# Plan: Self-Refreshing Code Index

## Created
2026-06-24

## Status
completed

## Objective
Make the `marko/codeindexer` `IndexCache` re-validate staleness on read so the long-lived `mcp:serve` and `lsp:serve` processes auto-refresh on additions, edits, and deletions — eliminating the need for a manual `indexer:rebuild` after scaffolding — and update the create-module skill and docs to stop teaching the obsolete `composer dump-autoload` / "verify via `list_modules`" / "reindex to pick up new code" reflexes.

## Related Issues
none

## Discovery Notes
Two real-world agent sessions (acta) wasted steps because the MCP tooling lied: an agent created a correct `app/Home/` module (marker + psr-4 + `#[Get('/')]`), but `validate_module`/`list_routes` returned "not found" / "no routes" until it ran `indexer:rebuild`.

Root cause — `packages/codeindexer/src/Cache/IndexCache.php`:
- `ensureLoaded()` (line ~144) short-circuits on `$this->data !== null` and never re-checks `isStale()` after the first read.
- `mcp:serve` (`packages/mcp/src/Server/McpServer.php`) and `lsp:serve` (`packages/lsp/src/Server/LspServer.php`) run one long-lived loop; `IndexCache` is registered as a **singleton** (`packages/codeindexer/module.php`). So both servers freeze their in-memory snapshot at first read for the whole process lifetime.
- `isStale()` (line ~157) already re-scans `app/*`, `modules/**`, `vendor/*/*` live via `ModuleWalker` and compares mtimes — it's just never *called* again. It detects additions/edits but cannot see deletions (mtime can't observe a removed file).

The runtime is NOT affected: routing (`RoutingBootstrapper::discoverRoutes`) and autoloading (`ModuleAutoloader::register`, run every boot from each module's own `composer.json` psr-4) are live per request. The site works immediately; only the AI tooling's symbol index lagged.

Decisions resolved during clarification:
- **Deletions: in scope.** Persist the tracked source-file path set in the cache and compare it on staleness check, so removals also trigger a rebuild. Cheap because the clean-case walk already happens; adds a path-set hash only.
- **Skip `vendor/` in the re-check.** Vendor changes only via Composer (where the cold-warm / `indexer:rebuild` path already applies), so the on-read staleness check walks only `app/*` + `modules/**` — the dirs an agent actually edits. `build()` still indexes vendor fully.
- **No TTL/clock.** With vendor excluded, the scoped walk is cheap enough to run on every read; re-checking every read is simpler (no clock seam, no extra task) and strictly more responsive than a memo — there is no window where the agent's own fresh write is masked. A rebuild still fires at most once per change (cache mtime is stamped on `save()`).

The docs already document the staleness as an *accepted limitation* (`packages/codeindexer.md:61`, `concepts/modularity.md:44`) — this plan reverses that, so those paragraphs are rewritten, not appended to.

The devai agent-guidance edit (`ClaudeCodeAgent.php` + its test) added earlier this session asserts "the index auto-rebuilds whenever stale, you never need `indexer:rebuild`"; that becomes accurate once this fix lands and is reconciled here.

## Scope

### In Scope
- `IndexCache` re-validates staleness on every read (not just first load), so long-lived servers auto-refresh.
- Re-check scoped to `app/*` + `modules/**` only; `vendor/` is excluded from the on-read check (still fully indexed by `build()`).
- Detect additions, edits, AND deletions (tracked path-set comparison) under the scoped dirs.
- No TTL/clock: re-check on every read (scoped walk is cheap; a rebuild fires at most once per change).
- Preserve all existing `IndexCache` behavior (lazy first load, build-on-missing, mtime invalidation, unserialize allowlist).
- Rewrite create-module `SKILL.md` Step 6 (drop `composer dump-autoload`; drop "verify via `list_modules`" gate; reframe verification around live runtime discovery + self-refreshing tooling).
- Rewrite docs that describe the staleness limitation / reindex-to-pick-up-new-code: `packages/codeindexer.md`, `concepts/modularity.md`, `ai-assisted-development/troubleshooting.md`, `ai-assisted-development/verification-checklist.md`.
- Reconcile the devai agent guidance (`ClaudeCodeAgent.php`) + its test so it matches the new behavior.

### Out of Scope
- Push-based file watching (inotify/fswatch) — refresh stays pull/lazy on read.
- Any change to runtime routing, module discovery, or autoloading (already correct).
- Changing the `indexer:rebuild` command itself (kept for forced clean rebuild / cold-cache warm-up).
- Renaming the `codeindexer` package or `indexer:rebuild` command.

## Success Criteria
- [ ] After a module/route is created under `app/`/`modules/` at runtime, the next MCP/LSP read reflects it without `indexer:rebuild` or a server restart (no TTL window).
- [ ] Edits to and deletions of tracked `app`/`modules` files are reflected on the next read.
- [ ] An explicit single-instance (singleton) test proves a populated `IndexCache` re-checks and refreshes on a later read — not only a fresh-object reload.
- [ ] A vendor-only change does NOT trigger an on-read rebuild (vendor excluded from the re-check); a rebuild fires at most once per change (no rebuild storm).
- [ ] All existing `IndexCache` tests still pass; runtime behavior unchanged.
- [ ] create-module Step 6 no longer mentions `composer dump-autoload` or frames `list_modules` as a discovery gate.
- [ ] No doc tells the reader to `indexer:rebuild` to make the tooling see `app`/`modules` code they just wrote (vendor/external changes may still mention it).
- [ ] `composer test` passes; touched files pass lint.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | IndexCache: re-validate staleness on read — app/+modules/ scope, additions/edits/deletions, no TTL | - | completed |
| 002 | Rewrite create-module SKILL.md Step 6 | 001 | completed |
| 003 | Docs rewrite: codeindexer.md + concepts/modularity.md | 001 | completed |
| 004 | Docs rewrite: troubleshooting.md + verification-checklist.md | 001 | completed |
| 005 | Reconcile devai agent guidance (ClaudeCodeAgent.php) + test | 001 | completed |

Dependency note: tasks 002–005 (skill + docs + devai guidance) all depend only on task 001 — the core behavior — and run in parallel once it lands. The TTL/clock task was dropped: with `vendor/` excluded from the re-check, the scoped walk is cheap enough to run on every read, so no memoization is needed (and re-checking on every read avoids any window where a fresh write is masked).

## Architecture Notes
- The fix is intentionally centralized in `IndexCache` so MCP, LSP, and any future consumer of the singleton all benefit — no per-server wiring. Confirmed: every MCP tool and LSP feature reads via the getters (`getModules`/`getRoutes`/…), all of which funnel through `ensureLoaded()`. Neither `McpServer` nor `LspServer` warms the cache at startup, so `ensureLoaded()` is the single correct seam.
- **No TTL/clock.** `vendor/` is excluded from the on-read staleness re-check, leaving only `app/*` + `modules/**` to walk — a few `stat()`s, single-digit ms — so re-checking on every read is cheap. This also avoids the one downside of a TTL: a window where the agent's own fresh write is masked. No constructor change is needed (no clock param), so the singleton stays container-resolvable as-is.
- **Rebuild-once-per-change.** `build()` → `save()` stamps the cache file mtime to "now", so after the first read rebuilds, subsequent reads see "not stale" until the next real change. At most one `build()` per change; no rebuild storm even without memoization.
- `build()` still walks **everything** (`vendor` + `modules` + `app`) so vendor stays fully indexed; only the *staleness re-check* is scoped. New/removed vendor packages surface on the next `indexer:rebuild` (or the `devai:install` cold-warm).
- Deletion detection: persist the sorted tracked-path set (or its hash) for `app`/`modules` inside the cache payload during `build()`; the scoped `isStale()` recomputes it from the current walk and compares. Combined verdict: stale if path-set differs OR any tracked file mtime > cache mtime.
- **Ordering trap (resolved in task 001):** `load()` calls `isStale()` *before* `$this->data` is populated, so the deletion comparison cannot read the persisted set from `$this->data` (it is null at that point). The persisted set must be read directly from the on-disk payload (or a helper that takes the on-disk set). A naive in-memory comparison reads null during `load()` and would either never detect deletions or false-positive every load.
- Preserve the unserialize allowlist; the path-set/hash is stored as a plain scalar/array so it needs no new allowed class. Old caches missing the key are treated as stale and rebuilt once.

## Risks & Mitigations
- **Perf of walking on every read**: bounded by scoping the re-check to `app/`+`modules/` (vendor excluded) — a cheap `stat()` sweep at interactive tool-call cadence.
- **Breaking existing `IndexCache` tests** (esp. "loads cache without re-scanning when fresh"): keep first-load semantics identical; the re-check only fires on reads *after* `$this->data` is populated. Run the full `IndexCacheTest` suite as a gate.
- **Vendor change invisible until rebuild**: accepted and intentional (vendor changes only via Composer, where the cold-warm/`indexer:rebuild` path already applies); documented in tasks 003/004.
- **Docs reversing a prior deliberate decision**: intentional and user-approved; rewrite (not append) the affected paragraphs so no contradictory guidance remains.
- **Stale-cache payload shape change** (adding path-set) could break old `.marko/index.cache` files: treat an absent path-set field as "stale" so old caches rebuild once, cleanly.
