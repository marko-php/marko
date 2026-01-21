# Task 014: Diff Engine

**Status**: pending
**Depends on**: 005, 009, 013
**Retry count**: 0

## Description
Create the diff engine that compares entity-defined schema (from SchemaBuilder) with the actual database state (from Introspector). This produces a SchemaDiff object describing all changes needed to synchronize the database.

## Context
- Related files: packages/database/src/Diff/SchemaDiff.php, TableDiff.php, ColumnDiff.php, DiffCalculator.php
- Patterns to follow: Value objects for diff results
- Used by db:diff command and migration generator

## Requirements (Test Descriptions)
- [ ] `it detects new tables that need to be created`
- [ ] `it detects tables that need to be dropped`
- [ ] `it detects new columns in existing tables`
- [ ] `it detects columns that need to be dropped`
- [ ] `it detects column type changes`
- [ ] `it detects column nullable changes`
- [ ] `it detects column default value changes`
- [ ] `it detects new indexes`
- [ ] `it detects indexes that need to be dropped`
- [ ] `it detects new foreign keys`
- [ ] `it detects foreign keys that need to be dropped`
- [ ] `it flags destructive operations (DROP) separately`
- [ ] `it returns empty diff when schema matches database`
- [ ] `it provides human-readable diff summary`

## Acceptance Criteria
- All requirements have passing tests
- Diff is driver-agnostic (works with any introspector)
- Destructive operations are clearly flagged
- Diff objects are immutable value objects

## Implementation Notes
(Left blank - filled in by programmer during implementation)
