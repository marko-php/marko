# Task 015: SQL Generator Interface

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Define the SqlGeneratorInterface that converts SchemaDiff objects into SQL statements. This interface handles both "up" (apply changes) and "down" (rollback changes) SQL generation.

## Context
- Related files: packages/database/src/Diff/SqlGeneratorInterface.php
- Patterns to follow: Interface pattern
- Used by migration generator to create migration files

## Requirements (Test Descriptions)
- [x] `it defines generateUp(SchemaDiff) returning array of SQL statements`
- [x] `it defines generateDown(SchemaDiff) returning array of SQL statements`
- [x] `it defines generateCreateTable(Table) returning SQL string`
- [x] `it defines generateDropTable(tableName) returning SQL string`
- [x] `it defines generateAddColumn(table, Column) returning SQL string`
- [x] `it defines generateDropColumn(table, columnName) returning SQL string`
- [x] `it defines generateModifyColumn(table, Column, oldColumn) returning SQL string`
- [x] `it defines generateAddIndex(table, Index) returning SQL string`
- [x] `it defines generateDropIndex(table, indexName) returning SQL string`
- [x] `it defines generateAddForeignKey(table, ForeignKey) returning SQL string`
- [x] `it defines generateDropForeignKey(table, keyName) returning SQL string`

## Acceptance Criteria
- All requirements have passing tests
- Interface is complete for all diff operations
- Supports both up and down generation
- No driver-specific code

## Implementation Notes
- Created SqlGeneratorInterface in packages/database/src/Diff/SqlGeneratorInterface.php
- Interface provides 11 methods for generating SQL from schema differences:
  - generateUp/generateDown: High-level methods that take SchemaDiff and return array of SQL statements
  - generateCreateTable/generateDropTable: Full table creation/deletion
  - generateAddColumn/generateDropColumn/generateModifyColumn: Column-level alterations
  - generateAddIndex/generateDropIndex: Index management
  - generateAddForeignKey/generateDropForeignKey: Foreign key constraint management
- Uses SchemaDiff, Table, Column, Index, and ForeignKey value objects from existing schema classes
- Driver-specific implementations (MySQL, PostgreSQL) will implement this interface to generate platform-specific SQL
