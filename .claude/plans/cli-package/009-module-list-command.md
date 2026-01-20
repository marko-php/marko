# Task 009: ModuleListCommand

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Create the built-in `module:list` command that displays all modules and their status. Shows module name, source (vendor/modules/app), and enabled status.

## Context
- Directory: `packages/core/src/Commands/ModuleListCommand.php`
- Pattern: Implements CommandInterface with #[Command] attribute
- Note: Will need access to Application's modules array (via constructor injection or other means)

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name module:list`
- [ ] `it has Command attribute with description Show all modules and their status`
- [ ] `it implements CommandInterface`
- [ ] `it outputs all discovered modules`
- [ ] `it displays module name for each module`
- [ ] `it displays module source for each module`
- [ ] `it displays enabled status for each module`
- [ ] `it returns exit code 0 on success`
- [ ] `it formats output with aligned columns`

## Acceptance Criteria
- All requirements have passing tests
- Command is discoverable via #[Command] attribute
- Output shows name, source, and enabled status
- Uses constructor injection for Application
- Code follows code standards
