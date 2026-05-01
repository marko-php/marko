# Task 047: Define Agent adapter contract + base class

**Status**: pending
**Depends on**: 046
**Retry count**: 0

## Description
Define the `AgentInterface` contract and `AbstractAgent` base class that every per-agent adapter (Claude Code, Codex, Cursor, etc.) implements. Models after Laravel Boost's `SupportsGuidelines`/`SupportsMcp`/`SupportsSkills` pattern.

## Context
- Namespace: `Marko\DevAi\Contracts\AgentInterface`, `Marko\DevAi\Agents\AbstractAgent`
- Interface methods: `name()`, `isInstalled()`, `writeGuidelines(GuidelinesContent $content)`, `registerMcpServer(McpRegistration $reg)`, `registerLspServer(?LspRegistration $reg)`, `distributeSkills(array $skills)`
- Supporting contracts: `SupportsGuidelines`, `SupportsMcp`, `SupportsLsp`, `SupportsSkills` (mix-and-match capabilities per agent)

## Requirements (Test Descriptions)
- [ ] `it defines AgentInterface with name and capability-detection methods`
- [ ] `it defines SupportsGuidelines SupportsMcp SupportsLsp SupportsSkills capability interfaces`
- [ ] `it provides AbstractAgent base class with default no-op implementations`
- [ ] `it allows adapters to opt into each capability independently`
- [ ] `it includes readonly value objects for GuidelinesContent McpRegistration LspRegistration SkillBundle`

## Acceptance Criteria
- Contract is extensible — community can add new Agent adapters without forking
- Capability interfaces are narrow and composable

## Implementation Notes
(Filled in by programmer during implementation)
