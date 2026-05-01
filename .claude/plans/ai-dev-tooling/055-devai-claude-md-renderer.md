# Task 055: Implement CLAUDE.md renderer with @AGENTS.md import

**Status**: pending
**Depends on**: 047, 054
**Retry count**: 0

## Description
Implement `ClaudeMdRenderer` that produces `CLAUDE.md`. Uses Claude Code's `@AGENTS.md` import syntax to include the canonical content, then appends Claude-specific additions (plan mode hints, skill references, MCP tool pointers).

## Context
- Namespace: `Marko\DevAi\Rendering\ClaudeMdRenderer`
- Output starts with `@AGENTS.md` line per Claude Code's official pattern
- Appends Claude-specific sections below the import

## Requirements (Test Descriptions)
- [ ] `it renders CLAUDE.md starting with @AGENTS.md import line`
- [ ] `it appends Claude Code specific sections below the import`
- [ ] `it references marko-mcp and marko-lsp tool names where useful`
- [ ] `it produces deterministic output`
- [ ] `it includes regeneration marker`

## Acceptance Criteria
- Snapshot test locks format
- Import works correctly in Claude Code (manual verification or integration smoke)

## Implementation Notes
(Filled in by programmer during implementation)
