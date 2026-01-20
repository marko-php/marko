# Task 008: ListCommand

**Status**: completed
**Depends on**: 001, 003, 005
**Retry count**: 0

## Description
Create the built-in `list` command that displays all available commands. This is a core command that shows command names and descriptions in a formatted list.

## Context
- Directory: `packages/core/src/Commands/ListCommand.php`
- Pattern: Implements CommandInterface with #[Command] attribute
- Dependency: Needs CommandRegistry injected via constructor

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name list`
- [ ] `it has Command attribute with description Show all available commands`
- [ ] `it implements CommandInterface`
- [ ] `it outputs all registered commands`
- [ ] `it displays command name and description for each command`
- [ ] `it returns exit code 0 on success`
- [ ] `it formats output with aligned columns`
- [ ] `it shows message when no commands available`

## Acceptance Criteria
- All requirements have passing tests
- Command is discoverable via #[Command] attribute
- Output is readable and formatted
- Uses constructor injection for dependencies
- Code follows code standards
