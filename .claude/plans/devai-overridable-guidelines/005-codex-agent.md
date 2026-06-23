# Task 005: CodexAgent — route AGENTS.md through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Replace `CodexAgent`'s unconditional `file_put_contents($projectRoot . '/AGENTS.md', ...)` with a `GuidelinesWriter` call so a user-edited AGENTS.md is no longer clobbered. MCP registration and skill distribution stay untouched.

## Context
- File: `packages/devai/src/Agents/CodexAgent.php` — `writeGuidelines()` (lines 46-51).
- Currently clobbers AGENTS.md unconditionally (one of the two offenders).
- `registerMcpServer()` and `distributeSkills()` are OUT OF SCOPE — leave untouched.
- **Breaking existing assertion to update:** `CodexAgentTest.php` line 29 asserts `file_get_contents($root . '/AGENTS.md')->toBe('# Marko Guidelines')`. Marker-wrapping breaks byte equality — change to `->toContain('# Marko Guidelines')` and assert presence of `<!-- BEGIN marko:devai -->`.
- Surface the writer's `SkippedNoMarkers` notice via `GuidelinesWriter`'s static collector (orchestrator drains it; wired in task 010) — this agent does not print directly.
- Tests: `packages/devai/tests/Unit/Agents/CodexAgentTest.php`.

## Requirements (Test Descriptions)
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it preserves user content outside the markers in an existing AGENTS.md`
- [x] `it marker-merges the guideline body into AGENTS.md on update`
- [x] `it does not modify MCP registration or skill distribution behavior`

## Acceptance Criteria
- All requirements have passing tests
- No unconditional clobber of AGENTS.md remains
- MCP/skills code paths unchanged
- Code follows code standards

## Implementation Notes
- Replaced `file_put_contents($projectRoot . '/AGENTS.md', $content->body)` with `GuidelinesWriter::write($projectRoot . '/AGENTS.md', $content->body)` in `CodexAgent::writeGuidelines()`.
- Added `use Marko\DevAi\Writing\GuidelinesWriter;` import.
- Updated the existing `writes canonical AGENTS.md` test assertion from `->toBe('# Marko Guidelines')` to `->toContain('# Marko Guidelines') ->and(...)->toContain('<!-- BEGIN marko:devai -->')`.
- Added 4 new tests covering all requirements. Requirements 2 and 3 passed immediately since `GuidelinesWriter::write()` handles those scenarios natively.
- `registerMcpServer()` and `distributeSkills()` left entirely untouched.
