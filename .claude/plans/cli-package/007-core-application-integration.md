# Task 007: Core Application Integration

**Status**: pending
**Depends on**: 004, 005, 006
**Retry count**: 0

## Description
Integrate command discovery into the Application boot process. Add command registry and runner as properties on Application, discover commands during boot like plugins and observers.

## Context
- Related files: `packages/core/src/Application.php`
- Pattern: Follow existing discoverPlugins(), discoverObservers() patterns
- Exposes: commandRegistry, commandRunner on Application

## Requirements (Test Descriptions)
- [ ] `it discovers commands during application boot`
- [ ] `it exposes commandRegistry property on Application`
- [ ] `it exposes commandRunner property on Application`
- [ ] `it registers commands from all enabled modules`
- [ ] `it skips modules without src directory`
- [ ] `it skips modules without command classes`
- [ ] `it makes commandRunner available after boot`

## Acceptance Criteria
- All requirements have passing tests
- Follows existing discovery patterns in Application
- CommandRunner is properly wired with container
- No breaking changes to existing Application behavior
- Code follows code standards
