# Task 007: GeminiCliAgent — route AGENTS.md + GEMINI.md through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Route both files Gemini writes through `GuidelinesWriter`: the root AGENTS.md (only-if-absent today) and GEMINI.md (clobbered every run today). GEMINI.md is a root-level, user-facing file like CLAUDE.md, so it must be marker-merged and back off if markers are stripped. MCP registration and skill distribution stay untouched.

## Context
- File: `packages/devai/src/Agents/GeminiCliAgent.php` — `writeGuidelines()` (writes GEMINI.md unconditionally + AGENTS.md only-if-absent).
- `registerMcpServer()` and `distributeSkills()` are OUT OF SCOPE — leave untouched.
- Body duplication into GEMINI.md stays (now marker-synced); do NOT convert to an `@AGENTS.md` import (out of scope).
- **Breaking existing assertions to update:** `GeminiCliAgentTest.php` line 33 (`GEMINI.md ->toBe('# Marko Guidelines')`) breaks under marker-wrapping — change to `->toContain(...)` + marker-presence. Line 43 (`AGENTS.md ->toBe('existing')`) currently tests only-if-absent; it now becomes the marker-strip back-off case (a marker-less existing AGENTS.md is left byte-for-byte untouched, so `->toBe('existing')` still holds — keep it, update the test name/rationale to reflect back-off).
- Surface the writer's `SkippedNoMarkers` notice via `GuidelinesWriter`'s static collector (orchestrator drains it; wired in task 010).
- Tests: `packages/devai/tests/Unit/Agents/GeminiCliAgentTest.php`.

## Requirements (Test Descriptions)
- [x] `it creates GEMINI.md via the writer when it does not exist`
- [x] `it preserves user content outside the markers in an existing GEMINI.md`
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it leaves a marker-stripped GEMINI.md untouched`
- [x] `it does not modify Gemini MCP registration or skill distribution behavior`

## Acceptance Criteria
- All requirements have passing tests
- Both GEMINI.md and AGENTS.md route through the writer
- MCP/skills code paths unchanged
- Code follows code standards

## Implementation Notes
- Replaced `file_put_contents` calls in `writeGuidelines()` with `GuidelinesWriter::write()` for both GEMINI.md and AGENTS.md.
- The `if (!is_file($agentsPath))` guard was removed — `GuidelinesWriter::write()` handles create-if-absent / marker-merge / back-off natively.
- Updated `GeminiCliAgentTest.php`: replaced the two old byte-equality assertions with 5 new tests matching the requirement descriptions; added `GuidelinesWriter` and `SkillBundle` imports; removed the duplicate old MCP test and merged into the new "does not modify Gemini MCP registration or skill distribution behavior" test.
- `registerMcpServer()` and `distributeSkills()` were not touched.
