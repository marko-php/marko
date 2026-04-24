# Task 050: Implement Cursor agent adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the Cursor adapter. Writes `.cursor/rules/marko.mdc` (Project Rules) and `.cursor/mcp.json` for MCP registration. AGENTS.md also read by Cursor 1.0+.

## Context
- Namespace: `Marko\DevAi\Agents\CursorAgent`
- Detects Cursor via `.cursor/` directory or `cursor` binary
- MCP registration: write JSON to `.cursor/mcp.json` with the server command
- Cursor supports LSP in newer versions — implement SupportsLsp optimistically with detection

## Requirements (Test Descriptions)
- [ ] `it reports name as cursor`
- [ ] `it detects Cursor installation`
- [ ] `it writes .cursor/rules/marko.mdc with Marko guidelines`
- [ ] `it writes or merges .cursor/mcp.json entry for marko-mcp`
- [ ] `it writes AGENTS.md if not present`
- [ ] `it supports Guidelines Mcp capabilities`

## Acceptance Criteria
- JSON merge is safe — doesn't clobber existing entries
- Rule file format matches Cursor Project Rules spec

## Implementation Notes
(Filled in by programmer during implementation)
