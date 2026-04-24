# Task 057: Implement skills distribution

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement `SkillsDistributor` that collects `resources/ai/skills/*/SKILL.md` from every installed `marko/*` package plus core skills from devai itself, and copies them to each enabled agent's expected location (`.claude/skills/`, `.agents/skills/`, etc.).

## Context
- Namespace: `Marko\DevAi\Skills\SkillsDistributor`
- Discovery via ModuleWalker
- Per-agent destination determined by the Agent adapter
- Core skills: `make-module`, `make-driver`, `make-service-contract`, `upgrade-marko-version` (placeholder content — populated over time)

## Requirements (Test Descriptions)
- [ ] `it discovers resources/ai/skills/ SKILL.md files across every module`
- [ ] `it copies skills to each enabled agent's destination path`
- [ ] `it preserves skill directory structure (name/SKILL.md plus supporting files)`
- [ ] `it handles skill name conflicts with first-wins plus warning`
- [ ] `it removes orphaned skills on update if source package is removed`
- [ ] `it ships a core skill set from devai own resources`

## Acceptance Criteria
- Distribution is idempotent
- Conflict detection surfaces loud warning to user

## Implementation Notes
(Filled in by programmer during implementation)
