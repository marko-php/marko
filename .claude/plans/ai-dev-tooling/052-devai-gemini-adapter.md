# Task 052: Implement Gemini CLI agent adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the Gemini CLI adapter. Writes `GEMINI.md` (Gemini-native) and ensures `AGENTS.md` is present. Registers MCP server via `gemini mcp add`.

## Context
- Namespace: `Marko\DevAi\Agents\GeminiCliAgent`
- Detects via `gemini` binary on PATH
- MCP registration: `gemini mcp add -s project -t stdio marko-mcp php marko mcp:serve`

## Requirements (Test Descriptions)
- [ ] `it reports name as gemini-cli`
- [ ] `it detects installation when gemini binary is on PATH`
- [ ] `it writes GEMINI.md with Marko guidelines`
- [ ] `it ensures AGENTS.md is present`
- [ ] `it registers marko-mcp via gemini mcp add command`
- [ ] `it supports Guidelines Mcp Skills capabilities`

## Acceptance Criteria
- Subprocess invocation mockable
- GEMINI.md and AGENTS.md content aligned

## Implementation Notes
(Filled in by programmer during implementation)
