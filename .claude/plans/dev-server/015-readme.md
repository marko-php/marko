# Task 015: Create README.md for marko/dev-server

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Create the README.md for the `marko/dev-server` package following the Package README Standards. Document the commands, configuration options, and usage patterns.

## Context
- Related files: `packages/dev-server/README.md`
- Follow format from `.claude/code-standards.md` "Package README Standards" section
- Sections: Title + One-Liner, Overview, Installation, Usage, Configuration, API Reference
- Lead with practical benefit
- Show zero-config usage first, then configuration options
- Document the `true | string | false` config pattern
- Document `--port` and `--detach` CLI flags
- Document aliases (`up`/`down`)

## Requirements (Test Descriptions)
- [ ] `it has README.md with required sections`
- [ ] `it documents installation via Composer`
- [ ] `it documents dev:up, dev:down, and dev:status commands`
- [ ] `it documents config/dev.php configuration`
- [ ] `it documents CLI flag overrides`

## Acceptance Criteria
- README exists and follows package standards
- All commands and configuration documented
- Code examples follow code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
