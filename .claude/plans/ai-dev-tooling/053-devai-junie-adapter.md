# Task 053: Implement JetBrains Junie agent adapter

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement the JetBrains Junie adapter. Writes content to `junie/` directory and ensures `AGENTS.md` is present. Junie is IDE-integrated and doesn't require explicit MCP CLI registration — provide MCP registration via its settings file format.

## Context
- Namespace: `Marko\DevAi\Agents\JunieAgent`
- Detects via JetBrains `.idea/` directory or `junie/` presence
- Distributes skills and guidelines to `junie/` layout expected by Junie

## Requirements (Test Descriptions)
- [ ] `it reports name as junie`
- [ ] `it detects JetBrains IDE or existing junie/ directory`
- [ ] `it writes junie/ layout with Marko guidelines`
- [ ] `it ensures AGENTS.md is present`
- [ ] `it supports Guidelines Skills capabilities`

## Acceptance Criteria
- Junie-expected file layout honored
- No conflicts with existing .idea/ files

## Implementation Notes
(Filled in by programmer during implementation)
