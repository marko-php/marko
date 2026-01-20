# Task 005: CommandRegistry

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create `CommandRegistry` that stores discovered commands and provides lookup by name. Similar to `ObserverRegistry` and `PluginRegistry` patterns.

## Context
- Related files: `packages/core/src/Event/ObserverRegistry.php`, `packages/core/src/Plugin/PluginRegistry.php`
- Directory: `packages/core/src/Command/CommandRegistry.php`
- Pattern: Registry for storing and retrieving command definitions

## Requirements (Test Descriptions)
- [ ] `it registers a CommandDefinition`
- [ ] `it retrieves command by name`
- [ ] `it returns null for unknown command name`
- [ ] `it returns all registered commands`
- [ ] `it checks if command exists by name`
- [ ] `it throws CommandException on duplicate command name registration`
- [ ] `it returns commands sorted alphabetically by name`

## Acceptance Criteria
- All requirements have passing tests
- Follows existing Registry patterns
- Loud error on duplicate registration
- Sorted output for consistent listing
- Code follows code standards
