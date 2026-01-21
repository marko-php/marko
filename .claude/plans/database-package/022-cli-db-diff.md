# Task 022: CLI db:diff Command

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Create the db:diff CLI command that compares entity-defined schema to the actual database and shows what changes would be made. This is a read-only operation for previewing migrations.

## Context
- Related files: packages/database/src/Command/DiffCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Shows changes without generating or applying anything

## Requirements (Test Descriptions)
- [x] `it registers as db:diff command via #[Command] attribute`
- [x] `it implements CommandInterface`
- [x] `it discovers entity classes with #[Table] from all modules`
- [x] `it builds schema from entity metadata`
- [x] `it introspects current database state`
- [x] `it calculates diff between entities and database`
- [x] `it displays tables to be created`
- [x] `it displays tables to be dropped (flagged as destructive)`
- [x] `it displays columns to be added`
- [x] `it displays columns to be dropped (flagged as destructive)`
- [x] `it displays columns to be modified`
- [x] `it displays indexes to be added or dropped`
- [x] `it displays "No changes detected" when in sync`
- [x] `it returns 0 when no changes, 1 when changes exist`

## Acceptance Criteria
- All requirements have passing tests
- Destructive operations clearly warned
- Output shows SQL that would be generated
- Read-only, makes no changes

## Implementation Notes
Created DiffCommand class that:
- Uses EntityDiscovery to find all entity classes with #[Table] attribute
- Uses EntityMetadataFactory and SchemaBuilder to convert entities to Table schema
- Uses IntrospectorInterface to get current database state
- Uses DiffCalculator to compute differences between entity schema and database
- Outputs human-readable diff with [DESTRUCTIVE] warnings for data-loss operations
- Returns exit code 0 when no changes, 1 when changes detected
