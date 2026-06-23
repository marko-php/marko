# Task 010: Update integration tests — monorepo + external project installs

**Status**: completed
**Depends on**: 002, 004, 005, 006, 007, 008, 009
**Retry count**: 0

## Description
Update the end-to-end install integration tests to assert the new override behavior across a full `devai:install` run: files are created with markers, re-running an install preserves user content outside markers, marker-stripped files are left untouched, and no generated file (except Claude's own artifacts) references `.claude/`.

## Context
- Files: `packages/devai/tests/Integration/ClaudeCodeInstallMonorepoTest.php`, `packages/devai/tests/Integration/ClaudeCodeInstallExternalProjectTest.php`, and `tests/Unit/Installation/InstallationOrchestratorTest.php` if affected.
- These run real installs into temp project roots — ideal for asserting the create → edit → re-install → preserved cycle. Re-install in these tests must pass `force: true` (the orchestrator short-circuits with a "skipped" status when `.marko/devai.json` already exists and `--force` is absent — `devai:update` always re-runs with force).
- **Orchestrator notice-draining wiring (production code change, belongs here).** After the agent loop in `InstallationOrchestrator::install()`, drain `GuidelinesWriter::takeNotices()` and append each returned message to `$this->log` so `SkippedNoMarkers` outcomes surface in the install/update command output. Without this, the "loud notice on skip" success criterion is unmet — the writer's collector (task 001) has no consumer otherwise. Add an orchestrator-level test asserting a marker-stripped guideline file produces a notice line in the returned `log`.
- **Multi-agent shared AGENTS.md:** the orchestrator invokes every selected agent against the same root `AGENTS.md`. The first agent creates it with markers; subsequent agents see existing markers and perform an idempotent in-marker update with the same body. This is expected and safe — assert that installing multiple agents (e.g. claude-code + codex) yields exactly one marker pair in AGENTS.md (no duplicated/nested markers).
- **core.md change (task 002) is exercised here:** the integration orchestrator uses the real `GuidelinesAggregator`, which reads the actual `resources/ai/guidelines/core.md`. After task 002 the generated AGENTS.md must contain no `.claude/` substring and must mention `search_docs`. Assert both.
- Use `composer test` (excludes slow destructive integration tests). Confirm MCP/LSP-related assertions still pass (no regression).

## Requirements (Test Descriptions)
- [x] `it creates marker-wrapped guideline files on a fresh install`
- [x] `it preserves user edits outside markers when install is re-run with force`
- [x] `it leaves marker-stripped files untouched on re-run`
- [x] `it surfaces a loud notice in the install log when a guideline file has its markers stripped`
- [x] `it writes exactly one marker pair in AGENTS.md when multiple agents are installed`
- [x] `it ships no dot-claude references in tool-agnostic generated files`
- [x] `it points generated guidelines at search_docs for deeper documentation`
- [x] `it keeps MCP and LSP registration behavior intact`

## Acceptance Criteria
- All requirements have passing tests
- Existing integration assertions updated, not deleted, where behavior intentionally changed
- `composer test` green
- Code follows code standards

## Implementation Notes
**Part A (production code):** Added `GuidelinesWriter::takeNotices()` drain in `InstallationOrchestrator::install()` after the agent loop. Each notice returned is appended to `$this->log`, ensuring `SkippedNoMarkers` outcomes surface in install/update command output. Added `use Marko\DevAi\Writing\GuidelinesWriter;` import.

**Part B (tests):** Added 8 tests to `ClaudeCodeInstallExternalProjectTest.php` (under a new `describe('override behavior')` block) and 1 unit test to `InstallationOrchestratorTest.php` for the loud-notice draining behavior.

Requirements 1, 2, 3, 5, 6, 7, 8 were already implemented by prior tasks (the `GuidelinesWriter` and `core.md` already provided the correct behavior). Requirement 4 required the production code change (Part A).

Final suite: 213 tests passed (525 assertions).
