# Task 016: MySQL SQL Generator

**Status**: completed
**Depends on**: 010, 015
**Retry count**: 0

## Description
Implement the MySQL-specific SQL generator that produces MySQL DDL statements from schema diffs. This handles MySQL's specific syntax for CREATE TABLE, ALTER TABLE, indexes, and foreign keys.

## Context
- Related files: packages/database-mysql/src/Sql/MySqlGenerator.php
- Patterns to follow: Implements SqlGeneratorInterface
- Uses backticks for identifiers, MySQL data types

## Requirements (Test Descriptions)
- [x] `it implements SqlGeneratorInterface`
- [x] `it generates CREATE TABLE with all column definitions`
- [x] `it generates DROP TABLE statements`
- [x] `it generates ALTER TABLE ADD COLUMN`
- [x] `it generates ALTER TABLE DROP COLUMN`
- [x] `it generates ALTER TABLE MODIFY COLUMN for type changes`
- [x] `it generates CREATE INDEX statements`
- [x] `it generates DROP INDEX statements`
- [x] `it generates ALTER TABLE ADD CONSTRAINT for foreign keys`
- [x] `it generates ALTER TABLE DROP FOREIGN KEY`
- [x] `it maps Column types to MySQL data types`
- [x] `it handles AUTO_INCREMENT for serial columns`
- [x] `it generates proper DEFAULT expressions`
- [x] `it generates down SQL that reverses up SQL`

## Acceptance Criteria
- All requirements have passing tests
- Generated SQL is valid MySQL syntax
- Type mapping covers common types
- DOWN migrations properly reverse UP migrations

## Implementation Notes
Implemented MySqlGenerator class that:
- Uses backticks for all identifier quoting
- Maps abstract types (integer, string, boolean, etc.) to MySQL types (INT, VARCHAR, TINYINT(1), etc.)
- Handles AUTO_INCREMENT for serial/primary key columns
- Generates proper DEFAULT expressions (quoted strings, numeric values, SQL expressions like CURRENT_TIMESTAMP)
- Supports all IndexType variants: Btree, Unique, Fulltext
- Generates complete CREATE TABLE statements with columns, primary keys, indexes, and foreign keys
- Generates ALTER TABLE statements for add/drop/modify column operations
- Generates CREATE INDEX and DROP INDEX statements
- Generates ADD CONSTRAINT and DROP FOREIGN KEY statements
- generateDown() properly reverses all operations from generateUp()
