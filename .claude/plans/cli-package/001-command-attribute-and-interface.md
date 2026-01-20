# Task 001: Command Attribute and Interface

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the foundational `#[Command]` attribute and `CommandInterface` that define how commands are declared and implemented. This follows the same pattern as `#[Observer]` with a required method contract.

## Context
- Related files: `packages/core/src/Attributes/Observer.php` (pattern to follow)
- Directory: `packages/core/src/Attributes/Command.php`, `packages/core/src/Command/CommandInterface.php`
- Pattern: Attribute on class, interface defines `execute()` method contract

## Requirements (Test Descriptions)
- [ ] `it creates Command attribute with name parameter`
- [ ] `it creates Command attribute with description parameter`
- [ ] `it creates Command attribute with optional description defaulting to empty string`
- [ ] `it targets only classes with Command attribute`
- [ ] `it marks Command attribute as readonly`
- [ ] `it defines CommandInterface with execute method signature`
- [ ] `it requires execute method to accept Input and Output parameters`
- [ ] `it requires execute method to return integer exit code`

## Acceptance Criteria
- All requirements have passing tests
- Attribute follows Observer pattern (readonly, class target)
- Interface is minimal and focused
- Code follows code standards
