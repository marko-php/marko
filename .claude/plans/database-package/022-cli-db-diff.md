# Task 022: CLI db:diff Command

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Create the db:diff CLI command that compares entity-defined schema to the actual database and shows what changes would be made. This is a read-only operation for previewing migrations.

## Context
- Related files: packages/database/src/Command/DiffCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Shows changes without generating or applying anything

## Requirements (Test Descriptions)
- [ ] `it registers as db:diff command via #[Command] attribute`
- [ ] `it implements CommandInterface`
- [ ] `it discovers entity classes with #[Table] from all modules`
- [ ] `it builds schema from entity metadata`
- [ ] `it introspects current database state`
- [ ] `it calculates diff between entities and database`
- [ ] `it displays tables to be created`
- [ ] `it displays tables to be dropped (flagged as destructive)`
- [ ] `it displays columns to be added`
- [ ] `it displays columns to be dropped (flagged as destructive)`
- [ ] `it displays columns to be modified`
- [ ] `it displays indexes to be added or dropped`
- [ ] `it displays "No changes detected" when in sync`
- [ ] `it returns 0 when no changes, 1 when changes exist`

## Acceptance Criteria
- All requirements have passing tests
- Destructive operations clearly warned
- Output shows SQL that would be generated
- Read-only, makes no changes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
