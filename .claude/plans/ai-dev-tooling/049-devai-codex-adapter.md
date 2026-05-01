# Task 049: Implement Codex agent adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the OpenAI Codex adapter. Writes canonical `AGENTS.md`, registers `marko-mcp` via `codex mcp add`, distributes skills to `.agents/skills/`.

## Context
- Namespace: `Marko\DevAi\Agents\CodexAgent`
- Detects installation via `codex` binary on PATH
- MCP registration: `codex mcp add marko-mcp -- php marko mcp:serve`
- LSP: Codex does not support custom LSP registration yet — adapter does not implement SupportsLsp

## Requirements (Test Descriptions)
- [ ] `it reports name as codex`
- [ ] `it detects installation when codex binary is on PATH`
- [ ] `it writes canonical AGENTS.md with Marko guidelines`
- [ ] `it registers marko-mcp via codex mcp add command with correct argument separator`
- [ ] `it distributes skills to .agents/skills directory`
- [ ] `it supports Guidelines Mcp Skills capabilities but not Lsp`

## Acceptance Criteria
- Subprocess invocation mockable
- AGENTS.md content is the canonical shared content (same file Copilot etc. read)

## Implementation Notes
(Filled in by programmer during implementation)
