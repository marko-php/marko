# Task 003: Update CommandRunner for alias resolution

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
CommandRunner already delegates to `CommandRegistry::get()` which now handles aliases. This task verifies that alias resolution works end-to-end through the runner, and that error messages for alias-resolved commands are helpful.

## Context
- Related files: `packages/core/src/Command/CommandRunner.php`
- The runner calls `$this->registry->get($commandName)` — if CommandRegistry handles aliases in `get()`, the runner may need no changes
- Tests: `packages/core/tests/Command/CommandRunnerTest.php`
- Verify integration works by testing that running an alias name executes the correct command

## Requirements (Test Descriptions)
- [ ] `it executes command via alias name`
- [ ] `it returns correct exit code when invoked via alias`
- [ ] `it passes Input and Output when invoked via alias`

## Acceptance Criteria
- All requirements have passing tests
- Existing CommandRunner tests still pass
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
