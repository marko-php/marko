# Task 006: CommandRunner

**Status**: pending
**Depends on**: 003, 005
**Retry count**: 0

## Description
Create `CommandRunner` that executes commands by name. Resolves command from registry, instantiates via container, and calls execute() with Input/Output.

## Context
- Directory: `packages/core/src/Command/CommandRunner.php`
- Dependencies: ContainerInterface, CommandRegistry
- Pattern: Takes command name, resolves and executes

## Requirements (Test Descriptions)
- [ ] `it executes command by name`
- [ ] `it instantiates command class via container`
- [ ] `it passes Input to execute method`
- [ ] `it passes Output to execute method`
- [ ] `it returns exit code from command execute method`
- [ ] `it throws CommandException when command not found`
- [ ] `it returns exit code 0 on successful execution`
- [ ] `it returns non-zero exit code on command failure`

## Acceptance Criteria
- All requirements have passing tests
- Uses container for command instantiation (enables DI)
- Clean separation of concerns
- Proper error handling
- Code follows code standards
