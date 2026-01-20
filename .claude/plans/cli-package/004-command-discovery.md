# Task 004: CommandDiscovery

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create `CommandDiscovery` class that scans modules for classes with `#[Command]` attribute. Follows the same pattern as `ObserverDiscovery` - scans src directories, validates required methods exist.

## Context
- Related files: `packages/core/src/Event/ObserverDiscovery.php` (pattern to follow)
- Directory: `packages/core/src/Command/CommandDiscovery.php`
- Uses: `ClassFileParser`, reflection to find `#[Command]` attributes

## Requirements (Test Descriptions)
- [ ] `it discovers command classes in module src directories`
- [ ] `it ignores classes without Command attribute`
- [ ] `it ignores directories without src folder`
- [ ] `it returns array of CommandDefinition objects`
- [ ] `it extracts command name from attribute`
- [ ] `it extracts description from attribute`
- [ ] `it throws CommandException when command class missing execute method`
- [ ] `it throws CommandException when command class does not implement CommandInterface`
- [ ] `it discovers commands from multiple modules`

## Acceptance Criteria
- All requirements have passing tests
- Follows ObserverDiscovery pattern exactly
- Creates CommandException for error cases
- Uses ClassFileParser for file scanning
- Code follows code standards
