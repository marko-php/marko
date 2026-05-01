# Task 054: Implement canonical AGENTS.md renderer

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement `AgentsMdRenderer` that produces the canonical `AGENTS.md` content consumed by every adapter. Renders Marko-specific guidelines, conventions, commands, testing, code standards into the agents.md format per https://agents.md spec.

## Context
- Namespace: `Marko\DevAi\Rendering\AgentsMdRenderer`
- Input: `GuidelinesContent` aggregated from core + third-party packages (task 056)
- Output: single Markdown string with stable section ordering (project overview, build commands, test commands, code style, security, testing)

## Requirements (Test Descriptions)
- [ ] `it renders a complete AGENTS.md with project overview build test code-style security testing sections`
- [ ] `it includes Marko commands from composer scripts and #[Command] attributes`
- [ ] `it aggregates guidelines from marko/core and third-party contributors deterministically`
- [ ] `it produces byte-for-byte identical output on repeated runs given identical input`
- [ ] `it includes a "do not edit" marker with regeneration instructions`

## Acceptance Criteria
- Snapshot test locks output format
- Output validates against agents.md structural conventions

## Implementation Notes
(Filled in by programmer during implementation)
