# Task 048: Implement Claude Code agent adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the Claude Code adapter. Writes `CLAUDE.md` with `@AGENTS.md` import, registers `marko-mcp` as an MCP server via `claude mcp add`, registers `marko-lsp` via Claude Code plugin `.lsp.json`, distributes skills to `.claude/skills/`.

## Context
- Namespace: `Marko\DevAi\Agents\ClaudeCodeAgent`
- Detects installation via presence of `claude` binary on PATH
- Writes project-local config (not user-global) by default
- MCP registration via subprocess: `claude mcp add -s local -t stdio marko-mcp php marko mcp:serve`
- LSP registration via writing `.claude/plugins/marko/.lsp.json` with the lsp:serve command

## Requirements (Test Descriptions)
- [ ] `it reports name as claude-code`
- [ ] `it detects installation when claude binary is on PATH`
- [ ] `it writes CLAUDE.md with @AGENTS.md import and Claude-specific additions`
- [ ] `it registers marko-mcp via claude mcp add command`
- [ ] `it writes .lsp.json plugin config for marko-lsp`
- [ ] `it distributes skills to .claude/skills directory`
- [ ] `it supports all four capability interfaces Guidelines Mcp Lsp Skills`

## Acceptance Criteria
- Subprocess invocation is mockable in tests
- Idempotent — re-running doesn't duplicate registrations

## Implementation Notes
(Filled in by programmer during implementation)
