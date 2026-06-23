# Task 004: ClaudeCodeAgent — route AGENTS.md + CLAUDE.md through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Replace `ClaudeCodeAgent`'s unconditional `file_put_contents` writes of AGENTS.md and CLAUDE.md with `GuidelinesWriter` calls. AGENTS.md is created-if-absent and otherwise marker-merged; CLAUDE.md's marker region wraps the `@AGENTS.md` import plus the Claude-specific tooling block (plugins/MCP/LSP), with everything outside the markers owned by the user. The hardcoded `buildClaudeMd()` heredoc becomes the generated content passed into the writer.

## Context
- File: `packages/devai/src/Agents/ClaudeCodeAgent.php` — `writeGuidelines()` (lines 49-55) and `buildClaudeMd()` (lines 100-136).
- Today both files are clobbered unconditionally — this is the primary bug.
- MCP/LSP/settings logic (`writeSettings`, `ensureLspDeps`, cleanup helpers) is OUT OF SCOPE — leave untouched.
- The `@AGENTS.md` import line must remain (Claude reads CLAUDE.md, imports AGENTS.md), and it must sit INSIDE the marker region (the user owns content outside markers).
- **Breaking existing assertion to update (not just add):** `ClaudeCodeAgentTest.php` line 47 asserts `file_get_contents($this->root . '/AGENTS.md')->toBe('# Project Guidelines')` (exact byte equality). Once the body is marker-wrapped this is no longer byte-equal — change it to `->toContain('# Project Guidelines')` and add a separate assertion that the output contains `<!-- BEGIN marko:devai -->`. The CLAUDE.md `toContain(...)` substring assertions (lines 50–99) survive marker-wrapping unchanged — leave them.
- Surface the writer's `SkippedNoMarkers` notice via the static collector defined in task 001 (the orchestrator drains `GuidelinesWriter::takeNotices()` — wired in task 010); this agent does not print directly.
- Tests: `packages/devai/tests/Unit/Agents/ClaudeCodeAgentTest.php` plus integration tests (handled in task 010).

## Requirements (Test Descriptions)
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it preserves user content outside the markers in an existing AGENTS.md`
- [x] `it creates CLAUDE.md with the AGENTS.md import inside the marker region`
- [x] `it wraps the Claude tooling block inside the marker region`
- [x] `it leaves a marker-stripped CLAUDE.md untouched`
- [x] `it does not modify MCP or settings behavior`

## Acceptance Criteria
- All requirements have passing tests
- No unconditional clobber of AGENTS.md or CLAUDE.md remains
- MCP/LSP/settings code paths unchanged
- Code follows code standards

## Implementation Notes
- Replaced both `file_put_contents` calls in `writeGuidelines()` with `GuidelinesWriter::write()` calls.
- Added `use Marko\DevAi\Writing\GuidelinesWriter;` import to `ClaudeCodeAgent.php`.
- The `buildClaudeMd()` heredoc (containing `@AGENTS.md` import and Claude tooling block) becomes the `$generated` string passed to the writer — so the entire content sits inside the marker region.
- CLAUDE.md is treated like AGENTS.md: created-if-absent with markers, or marker-merged on subsequent runs. When markers are absent (user owns the file), writer backs off and emits a notice via `GuidelinesWriter::takeNotices()`.
- Updated the existing `->toBe('# Project Guidelines')` assertion to `->toContain(...)` since the writer now wraps content in markers.
- Added all 6 requirement tests plus kept the original 23 tests intact (29 total in file).
- All tests verified passing with PHP 8.5.1 at `/opt/homebrew/Cellar/php/8.5.1_2/bin/php`.
