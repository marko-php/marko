# Task 004: Update ListCommand to display aliases

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Update `ListCommand` to show aliases next to command names in the output. When a command has aliases, display them in a clear format so developers know both the full name and shorthand.

## Context
- Related files: `packages/core/src/Commands/ListCommand.php`
- Current format: `  command:name    Description text`
- Proposed format with alias: `  dev:up (up)    Start the development environment`
- Aliases shown in parentheses after the command name
- Commands without aliases display unchanged
- Tests: `packages/core/tests/Command/ListCommandTest.php`

## Requirements (Test Descriptions)
- [ ] `it displays alias in parentheses after command name`
- [ ] `it displays multiple aliases separated by commas`
- [ ] `it does not show parentheses for commands without aliases`
- [ ] `it aligns descriptions correctly when some commands have aliases`

## Acceptance Criteria
- All requirements have passing tests
- Existing ListCommand tests still pass
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
