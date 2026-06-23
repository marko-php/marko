# Task 008: CopilotAgent — route AGENTS.md + copilot-instructions.md through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Route both files Copilot writes through `GuidelinesWriter`: the root AGENTS.md (only-if-absent today) and `.github/copilot-instructions.md` (clobbered every run today). Both become marker-merged with back-off when markers are stripped. The `.vscode/mcp.json` registration stays untouched.

## Context
- File: `packages/devai/src/Agents/CopilotAgent.php` — `writeGuidelines()` (writes `.github/copilot-instructions.md` unconditionally + AGENTS.md only-if-absent).
- `registerMcpServer()` (writes `.vscode/mcp.json`) is OUT OF SCOPE — leave untouched.
- **Breaking existing assertions to update:** `CopilotAgentTest.php` line 37 (`.github/copilot-instructions.md ->toBe('# Marko Guidelines')`) and line 46 (`AGENTS.md ->toBe('# Marko Guidelines')`) break under marker-wrapping — change to `->toContain(...)` + marker-presence. Line 49 (`AGENTS.md ->toBe('# Marko Guidelines')` when pre-existing) currently tests only-if-absent; re-target it to the marker-strip back-off case (a marker-less existing AGENTS.md is left untouched).
- Surface the writer's `SkippedNoMarkers` notice via `GuidelinesWriter`'s static collector (orchestrator drains it; wired in task 010).
- Tests: `packages/devai/tests/Unit/Agents/CopilotAgentTest.php`.

## Requirements (Test Descriptions)
- [x] `it creates copilot-instructions.md via the writer when it does not exist`
- [x] `it preserves user content outside the markers in an existing copilot-instructions.md`
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it leaves a marker-stripped copilot-instructions.md untouched`
- [x] `it does not modify Copilot MCP registration behavior`

## Acceptance Criteria
- All requirements have passing tests
- Both copilot-instructions.md and AGENTS.md route through the writer
- MCP code path unchanged
- Code follows code standards

## Implementation Notes
- `CopilotAgent::writeGuidelines()` now calls `GuidelinesWriter::write()` for both `.github/copilot-instructions.md` and `AGENTS.md` instead of `file_put_contents()`. The old only-if-absent guard for AGENTS.md is replaced by the writer's `SkippedNoMarkers` back-off.
- Added `use Marko\DevAi\Writing\GuidelinesWriter;` import to `CopilotAgent.php`.
- Removed unused `$agentsPath` is-file guard; writer handles all three outcomes: Created / Updated / SkippedNoMarkers.
- `CopilotAgentTest.php` fully replaced: old byte-equality assertions swapped for `->toContain(...)` + marker presence; new tests added for marker-merge (preserves user content) and marker-strip back-off (untouched).
- `CursorAgentTest.php` also had a stale `->toBe('# Marko Guidelines')` assertion on AGENTS.md (pre-existing regression from when CursorAgent was already routed through the writer). Updated to `->toContain(...)` + marker check to fix the broken test in the same package.
