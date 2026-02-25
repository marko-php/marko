# Task 002: Update CommandRegistry for alias indexing

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `CommandRegistry` to index commands by their aliases in addition to their primary name. Alias lookups should return the same `CommandDefinition` as the primary name. Alias conflicts with existing names or other aliases throw `CommandException`.

## Context
- Related files: `packages/core/src/Command/CommandRegistry.php`, `packages/core/src/Exceptions/CommandException.php`
- Patterns to follow: Existing `register()`, `get()`, `has()`, `all()` methods
- The registry currently stores commands in `$this->commands` indexed by name
- Need a separate `$this->aliases` map (alias → primary name) or index aliases directly into `$this->commands`
- `all()` should NOT return duplicate entries for aliased commands
- Tests: `packages/core/tests/Command/CommandRegistryTest.php`

## Requirements (Test Descriptions)
- [ ] `it resolves command by alias`
- [ ] `it reports alias exists via has method`
- [ ] `it throws CommandException when alias conflicts with existing command name`
- [ ] `it throws CommandException when alias conflicts with another alias`
- [ ] `it does not duplicate aliased commands in all method`
- [ ] `it registers command with multiple aliases`

## Acceptance Criteria
- All requirements have passing tests
- Existing CommandRegistry tests still pass
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
