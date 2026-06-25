# Task 003: Docs rewrite — codeindexer.md + concepts/modularity.md

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Two docs pages currently document the index staleness as an accepted limitation and tell readers to run `indexer:rebuild` to make the tooling see new code. The fix in task 001 reverses that, so rewrite (not append to) the affected passages to describe the new self-refreshing behavior: the running MCP/LSP server re-checks staleness **on every read** and auto-refreshes on additions, edits, and deletions under `app/` and `modules/`. `indexer:rebuild` is now only for forcing a clean rebuild, warming a cold cache, or picking up **vendor** changes (a `composer require`/`update`), which the on-read check intentionally skips.

## Context
Documentation task — no unit test; verify by reading the rendered passages and confirming no contradictory "must reindex to see new code" language remains.

- Related files:
  - `docs/src/content/docs/packages/codeindexer.md` — the "lazy-loads / rebuilds itself if stale" note (~22, ~52) and especially the staleness-limitation paragraph (~61, "won't re-check staleness afterward … until you run `marko indexer:rebuild`")
  - `docs/src/content/docs/concepts/modularity.md` — the reindex note (~44, "If your AI tooling doesn't yet list the new module … run `marko indexer:rebuild` and reload")
- Behavior to describe accurately (per task 001):
  - The running server re-checks staleness on each read; **no TTL window** — a fresh write is visible on the very next tool read.
  - Re-check scope is `app/` + `modules/` only. **Vendor is excluded** from the on-read check; new/removed vendor packages need an explicit `indexer:rebuild` (or the cold-warm during `devai:install`).
  - Additions, edits, AND deletions under `app/`/`modules/` are picked up automatically.
- Patterns to follow: existing docs voice; keep the distinction between the **app** (always live) and the **tooling index** (now self-refreshing for `app`/`modules`).

## Requirements (Test Descriptions)
- [x] `it rewrites the codeindexer staleness paragraph to state the running server re-checks staleness on every read`
- [x] `it states additions, edits, and deletions under app and modules are picked up automatically without indexer:rebuild`
- [x] `it documents that vendor changes are excluded from the on-read check and still need indexer:rebuild`
- [x] `it reframes indexer:rebuild as a forced/clean-rebuild, cold-warm, and vendor-change tool, not a routine step`
- [x] `it rewrites the modularity reindex note to reflect tooling self-refresh`
- [x] `it removes any claim that newly created app/modules code stays invisible to tooling until a manual rebuild`

## Acceptance Criteria
- The `codeindexer.md` paragraph describing "won't re-check staleness afterward" is replaced with the on-read self-refresh behavior (no-TTL, app/modules scope, vendor excluded).
- `modularity.md` no longer instructs a reindex to surface a freshly created module in tooling.
- `indexer:rebuild` is still documented, but as forced/clean-rebuild + cold-warm + vendor-change.
- No contradiction with the create-module skill (task 002) or troubleshooting/checklist (task 004).

## Implementation Notes
- `codeindexer.md` line 22: updated "lazy-loads on first read and rebuilds itself if stale" → "lazy-loads on first read and re-checks staleness on every subsequent read"
- `codeindexer.md` line 52: rewrote the rebuilding-section intro to state no-TTL, on-every-read, app/modules scope; changed "force a rebuild" → "force a clean rebuild"
- `codeindexer.md` lines 60-62: replaced `:::caution[Long-running servers hold the index in memory]` block (which said "won't re-check staleness afterward") with `:::note[On-read self-refresh covers app/ and modules/; vendor needs an explicit rebuild]` describing on-read self-refresh, vendor exclusion, and the three remaining `indexer:rebuild` scenarios
- `modularity.md` line 44: rewrote reindex note — removed "run `marko indexer:rebuild` and reload" instruction, replaced with description of on-read self-refresh for app/modules and vendor as the sole remaining `indexer:rebuild` case
