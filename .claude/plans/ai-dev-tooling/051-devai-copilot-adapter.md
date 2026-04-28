# Task 051: Implement GitHub Copilot adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the GitHub Copilot adapter. Writes `.github/copilot-instructions.md` with Marko guidelines and ensures `AGENTS.md` is present (Copilot reads it as a fallback).

## Context
- Namespace: `Marko\DevAi\Agents\CopilotAgent`
- Detects installation via `.github/` directory presence or user-supplied opt-in
- MCP registration: write `.vscode/mcp.json` for VS Code Copilot MCP support

## Requirements (Test Descriptions)
- [ ] `it reports name as copilot`
- [ ] `it detects a .github directory in the project`
- [ ] `it writes .github/copilot-instructions.md with Marko guidelines`
- [ ] `it writes or ensures AGENTS.md exists as a shared canonical source`
- [ ] `it writes .vscode/mcp.json entry for marko-mcp`
- [ ] `it supports Guidelines Mcp capabilities`

## Acceptance Criteria
- Existing .github/copilot-instructions.md is preserved unless user confirms overwrite
- .vscode/mcp.json merges safely

## Implementation Notes
(Filled in by programmer during implementation)
