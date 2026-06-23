# Task 009: JunieAgent — route AGENTS.md + junie/guidelines.md through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Route both files Junie writes through `GuidelinesWriter`: the root AGENTS.md (only-if-absent today) and `junie/guidelines.md` (clobbered every run today). Both become marker-merged with back-off when markers are stripped. Skill distribution stays untouched.

## Context
- File: `packages/devai/src/Agents/JunieAgent.php` — `writeGuidelines()` (writes `junie/guidelines.md` unconditionally + AGENTS.md only-if-absent).
- `distributeSkills()` (syncs `junie/skills`) is OUT OF SCOPE — leave untouched.
- **Breaking existing assertions to update:** `JunieAgentTest.php` line 43 (`junie/guidelines.md ->toBe('# Marko Guidelines')`) and line 49 (`AGENTS.md ->toBe('# Marko Guidelines')`) break under marker-wrapping — change to `->toContain(...)` + marker-presence. Line 53 (`AGENTS.md ->toBe('# Custom')` when pre-existing) currently tests only-if-absent; re-target it to the marker-strip back-off case (a marker-less existing AGENTS.md is left byte-for-byte untouched, so `->toBe('# Custom')` still holds — update the test name/rationale).
- Surface the writer's `SkippedNoMarkers` notice via `GuidelinesWriter`'s static collector (orchestrator drains it; wired in task 010).
- Tests: `packages/devai/tests/Unit/Agents/JunieAgentTest.php`.

## Requirements (Test Descriptions)
- [x] `it creates junie guidelines.md via the writer when it does not exist`
- [x] `it preserves user content outside the markers in an existing junie guidelines.md`
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it leaves a marker-stripped junie guidelines.md untouched`
- [x] `it does not modify Junie skill distribution behavior`

## Acceptance Criteria
- All requirements have passing tests
- Both junie/guidelines.md and AGENTS.md route through the writer
- Skills code path unchanged
- Code follows code standards

## Implementation Notes
- Replaced direct `file_put_contents` calls with `GuidelinesWriter::write()` in `writeGuidelines()`
- Both `junie/guidelines.md` and `AGENTS.md` now use marker-merge with back-off when markers are stripped
- `distributeSkills()` left entirely untouched as specified
- Old tests updated: byte-equality assertions replaced with `->toContain(...)` + marker-presence checks
- marker-strip back-off covered by `it leaves a marker-stripped junie guidelines.md untouched`
- `GuidelinesWriter` import added to both the agent and test file
