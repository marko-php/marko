# Task 002: CommandDefinition Value Object

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `CommandDefinition` value object that holds metadata about a discovered command. This is similar to `ObserverDefinition` and `PluginDefinition` patterns in core.

## Context
- Related files: `packages/core/src/Event/ObserverDefinition.php`, `packages/core/src/Plugin/PluginDefinition.php`
- Directory: `packages/core/src/Command/CommandDefinition.php`
- Pattern: Readonly value object with command metadata

## Requirements (Test Descriptions)
- [ ] `it creates CommandDefinition with command class name`
- [ ] `it creates CommandDefinition with command name from attribute`
- [ ] `it creates CommandDefinition with description from attribute`
- [ ] `it marks CommandDefinition as readonly`
- [ ] `it exposes commandClass property`
- [ ] `it exposes name property`
- [ ] `it exposes description property`

## Acceptance Criteria
- All requirements have passing tests
- Follows existing Definition patterns in core
- Readonly for immutability
- Code follows code standards
