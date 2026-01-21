# Task 014: Diff Engine

**Status**: completed
**Depends on**: 005, 009, 013
**Retry count**: 0

## Description
Create the diff engine that compares entity-defined schema (from SchemaBuilder) with the actual database state (from Introspector). This produces a SchemaDiff object describing all changes needed to synchronize the database.

## Context
- Related files: packages/database/src/Diff/SchemaDiff.php, TableDiff.php, DiffCalculator.php
- Patterns to follow: Value objects for diff results
- Used by db:diff command and migration generator

## Requirements (Test Descriptions)
- [x] `it detects new tables that need to be created`
- [x] `it detects tables that need to be dropped`
- [x] `it detects new columns in existing tables`
- [x] `it detects columns that need to be dropped`
- [x] `it detects column type changes`
- [x] `it detects column nullable changes`
- [x] `it detects column default value changes`
- [x] `it detects new indexes`
- [x] `it detects indexes that need to be dropped`
- [x] `it detects new foreign keys`
- [x] `it detects foreign keys that need to be dropped`
- [x] `it flags destructive operations (DROP) separately`
- [x] `it returns empty diff when schema matches database`
- [x] `it provides human-readable diff summary`

## Acceptance Criteria
- All requirements have passing tests
- Diff is driver-agnostic (works with any introspector)
- Destructive operations are clearly flagged
- Diff objects are immutable value objects

## Implementation Notes
Created the following components:

1. **SchemaDiff** (`packages/database/src/Diff/SchemaDiff.php`)
   - Readonly value object holding `tablesToCreate`, `tablesToDrop`, `tablesToAlter`
   - Methods: `isEmpty()`, `hasDestructiveChanges()`, `getDestructiveChanges()`, `getSummary()`

2. **TableDiff** (`packages/database/src/Diff/TableDiff.php`)
   - Readonly value object holding column/index/foreign key changes
   - Properties: `columnsToAdd`, `columnsToDrop`, `columnsToModify`, `indexesToAdd`, `indexesToDrop`, `foreignKeysToAdd`, `foreignKeysToDrop`
   - Methods: `isEmpty()`, `hasDestructiveChanges()`, `getDestructiveChanges()`, `getSummaryLines()`

3. **DiffCalculator** (`packages/database/src/Diff/DiffCalculator.php`)
   - Main entry point: `calculate(array $entitySchema, array $databaseSchema): SchemaDiff`
   - Driver-agnostic: works with any schema represented as Table value objects
   - Compares by name for tables, columns, indexes, and foreign keys
   - Uses Column::equals() for detecting column modifications

4. **Table Schema Enhancement** (`packages/database/src/Schema/Table.php`)
   - Added `foreignKeys` property to Table class
   - Added `withForeignKey()` method for immutable building
   - Updated `equals()` method to include foreign key comparison

All tests pass (188 total in database package, 24 in Diff directory).
