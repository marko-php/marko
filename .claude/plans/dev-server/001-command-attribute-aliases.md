# Task 001: Add aliases to Command attribute and CommandDefinition

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Extend the `#[Command]` attribute to accept an optional `aliases` parameter and update `CommandDefinition` to store aliases. This is the foundation for the alias feature that allows commands like `dev:up` to also be invoked as `up`.

## Context
- Related files: `packages/core/src/Attributes/Command.php`, `packages/core/src/Command/CommandDefinition.php`
- Patterns to follow: Both are `readonly` classes with constructor property promotion
- The Command attribute currently has `name` and `description` parameters
- CommandDefinition currently has `commandClass`, `name`, and `description` parameters
- Tests: `packages/core/tests/Command/CommandAttributeTest.php`, `packages/core/tests/Command/CommandDefinitionTest.php`

## Requirements (Test Descriptions)
- [ ] `it accepts aliases parameter in Command attribute`
- [ ] `it defaults aliases to empty array when not provided`
- [ ] `it stores aliases in CommandDefinition`
- [ ] `it defaults aliases to empty array in CommandDefinition when not provided`
- [ ] `it preserves existing Command attribute behavior without aliases`

## Acceptance Criteria
- All requirements have passing tests
- Existing tests still pass (backward compatible)
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
