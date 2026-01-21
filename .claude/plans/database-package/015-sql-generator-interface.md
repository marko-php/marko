# Task 015: SQL Generator Interface

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Define the SqlGeneratorInterface that converts SchemaDiff objects into SQL statements. This interface handles both "up" (apply changes) and "down" (rollback changes) SQL generation.

## Context
- Related files: packages/database/src/Diff/SqlGeneratorInterface.php
- Patterns to follow: Interface pattern
- Used by migration generator to create migration files

## Requirements (Test Descriptions)
- [ ] `it defines generateUp(SchemaDiff) returning array of SQL statements`
- [ ] `it defines generateDown(SchemaDiff) returning array of SQL statements`
- [ ] `it defines generateCreateTable(Table) returning SQL string`
- [ ] `it defines generateDropTable(tableName) returning SQL string`
- [ ] `it defines generateAddColumn(table, Column) returning SQL string`
- [ ] `it defines generateDropColumn(table, columnName) returning SQL string`
- [ ] `it defines generateModifyColumn(table, Column, oldColumn) returning SQL string`
- [ ] `it defines generateAddIndex(table, Index) returning SQL string`
- [ ] `it defines generateDropIndex(table, indexName) returning SQL string`
- [ ] `it defines generateAddForeignKey(table, ForeignKey) returning SQL string`
- [ ] `it defines generateDropForeignKey(table, keyName) returning SQL string`

## Acceptance Criteria
- All requirements have passing tests
- Interface is complete for all diff operations
- Supports both up and down generation
- No driver-specific code

## Implementation Notes
(Left blank - filled in by programmer during implementation)
