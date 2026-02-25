# Task 005: Update CommandDiscovery to extract aliases

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `CommandDiscovery` to read the `aliases` parameter from the `#[Command]` attribute and pass it to `CommandDefinition`. Currently, discovery creates definitions with only `commandClass`, `name`, and `description`.

## Context
- Related files: `packages/core/src/Command/CommandDiscovery.php`
- Line ~87-91 in CommandDiscovery creates CommandDefinition — needs to pass `aliases: $attribute->aliases`
- Tests: `packages/core/tests/Command/CommandDiscoveryTest.php`
- The attribute instance already has the `aliases` property from Task 001

## Requirements (Test Descriptions)
- [ ] `it discovers aliases from Command attribute`
- [ ] `it creates CommandDefinition with empty aliases when none specified`
- [ ] `it creates CommandDefinition with aliases when specified in attribute`

## Acceptance Criteria
- All requirements have passing tests
- Existing CommandDiscovery tests still pass
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
