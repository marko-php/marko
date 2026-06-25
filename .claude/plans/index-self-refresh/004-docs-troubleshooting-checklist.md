# Task 004: Docs rewrite — troubleshooting.md + verification-checklist.md

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
The AI-assisted-development troubleshooting and verification-checklist pages describe the MCP/LSP index self-heal behavior and use `indexer:rebuild` as the fix for stale completions / missing modules. Update them to match the new self-refreshing behavior (task 001) so they don't keep steering agents and users toward a manual rebuild for `app`/`modules` changes the server now picks up on its own. Preserve the genuinely still-valid uses of `indexer:rebuild`: cold-cache pre-warm during `devai:install`, forced clean rebuild, and **vendor** changes (which the on-read check skips).

## Context
Documentation task — no unit test; verify by reading the passages.

- Related files:
  - `docs/src/content/docs/ai-assisted-development/troubleshooting.md` — cold-boot pre-warm (~47–53), "rebuilds automatically on next read" (~92–95), self-heal completions (~120–123)
  - `docs/src/content/docs/ai-assisted-development/verification-checklist.md` — lazy-load/auto-rebuild note (~66) and the `indexer:rebuild` + `list_modules` verification steps (~70–71)
- Note: the existing "rebuilds automatically on next read" / "self-heal" lines (troubleshooting ~95, ~120) were technically *false* for long-lived servers before this plan (the bug); after task 001 they become true for `app`/`modules`. Make them accurate, not aspirational.
- Patterns to follow: existing checklist/troubleshooting voice; keep the cold-boot pre-warm guidance (still real) but correct the "stays invisible until you rebuild" framing for live `app`/`modules` changes.

## Requirements (Test Descriptions)
- [x] `it updates troubleshooting self-heal language to state the running server re-checks staleness on every read`
- [x] `it keeps the devai:install cold-cache pre-warm guidance intact`
- [x] `it removes guidance to indexer:rebuild for app or modules changes the server now auto-detects`
- [x] `it documents indexer:rebuild as still required for vendor changes`
- [x] `it updates the verification-checklist auto-rebuild note to match the new behavior`

## Acceptance Criteria
- Troubleshooting no longer implies live `app`/`modules` edits stay invisible until a manual rebuild.
- Cold-boot pre-warm (`discovery:cache` + `indexer:rebuild` during `devai:install`) guidance is retained.
- `indexer:rebuild` documented as valid for forced/clean rebuild, cold start, and vendor changes.
- Verification checklist's auto-rebuild description matches the implemented behavior.
- Consistent with tasks 002 and 003.

## Implementation Notes
- `troubleshooting.md` line 95: replaced the old "IndexCache also rebuilds automatically on next read" sentence with accurate language: running server re-checks staleness on every read for `app/`+`modules/`; vendor/Composer changes still need `indexer:rebuild`.
- `troubleshooting.md` line 120: replaced "lazy-load and rebuild whenever a watched source file is newer than the cache, so completions usually self-heal" with the accurate on-read re-check description, adding explicit guidance that vendor/composer.json changes require `indexer:rebuild`.
- `verification-checklist.md` lines 66–69: replaced "lazy-load and auto-rebuild on first read" with the on-read re-check description; `indexer:rebuild` retained and framed correctly as cold-start pre-warm, forced clean rebuild, and vendor-change tool.
- Cold-boot pre-warm section (troubleshooting lines 47–54) left intact — still accurate and correct.
