# Task 005: Reconcile devai agent guidance (ClaudeCodeAgent.php) + test

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Earlier this session a paragraph was added to the devai-generated agent guidance (`ClaudeCodeAgent.php`) stating there is no build/reindex step and the MCP/LSP index "auto-rebuilds whenever it is stale, so you never need to run `indexer:rebuild`." That claim was aspirational until the index fix; with task 001 landed it is accurate for `app`/`modules` changes. Finalize and verify the guidance text and its test so the generated `CLAUDE.md` is correct and consistent with the rewritten skill/docs, and add the "trust your own write" epistemic so agents don't treat introspection as an existence gate.

## Context
The guidance is emitted between the `marko:devai` markers in every project's `CLAUDE.md` via `marko devai:update`. A test already asserts the new paragraph lands.

- Related files (verified line numbers):
  - `packages/devai/src/Agents/ClaudeCodeAgent.php` line 120 — the existing paragraph: `"There is no build, compile, or reindex step. … The marko-mcp/marko-lsp symbol index auto-rebuilds whenever it is stale, so you never need to run indexer:rebuild … If you are reaching for a reindex after scaffolding, that is a Magento reflex Marko does not have — skip it."` This is the paragraph to finalize; it already exists in the generated heredoc.
  - `packages/devai/tests/Unit/Agents/ClaudeCodeAgentTest.php` line 154 — existing assertion `toContain('There is no build, compile, or reindex step')`; line 176 already asserts `toContain('list_modules')`. Add the new "trust your own write" assertion as an additional `toContain`; do NOT alter the existing two.
- Nuance to honor: the index now re-checks on every read with **no TTL window**, so a fresh `app`/`modules` write *is* visible on the next tool read — but the deeper point is epistemic: the code exists because you wrote it, and the runtime serves it live regardless of the tooling index. Frame "trust your own write" that way; keep the guidance true (the auto-refresh covers `app`/`modules`; vendor changes still need `indexer:rebuild`, but agents rarely add vendor packages mid-task so the guidance need not belabor it).
- Patterns to follow: `toContain` assertions (additive, won't break siblings); keep the generated-block voice.

## Requirements (Test Descriptions)
- [x] `it writes CLAUDE.md stating no build, compile, or reindex step is needed`
- [x] `it writes CLAUDE.md telling the agent to trust code it just created rather than treating list_modules as an existence check`
- [x] `it keeps the guidance consistent with the self-refreshing index behavior`

## Acceptance Criteria
- The generated `CLAUDE.md` guidance accurately reflects the shipped self-refresh behavior (no overclaim, no contradiction with docs).
- Adds an explicit "trust your own write; introspection is for discovering pre-existing code, not confirming your own scaffolding" line.
- `ClaudeCodeAgentTest` passes, including the new assertion(s); no existing assertion regressed.
- Wording consistent with tasks 002–004.

## Implementation Notes
- Added "Trust your own writes" paragraph at the end of the MCP tools section in `ClaudeCodeAgent.php`. The paragraph is framed epistemically: the module/route exists because you wrote it and the runtime serves source files live; introspection tools are for discovering pre-existing code, not confirming scaffolding just created.
- Added new `it(...)` test in `ClaudeCodeAgentTest.php` asserting `toContain('introspection tools are for discovering pre-existing code')`.
- All 31 tests pass; php-cs-fixer reports no changes needed.
