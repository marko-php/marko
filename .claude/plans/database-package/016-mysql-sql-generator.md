# Task 016: MySQL SQL Generator

**Status**: pending
**Depends on**: 010, 015
**Retry count**: 0

## Description
Implement the MySQL-specific SQL generator that produces MySQL DDL statements from schema diffs. This handles MySQL's specific syntax for CREATE TABLE, ALTER TABLE, indexes, and foreign keys.

## Context
- Related files: packages/database-mysql/src/Sql/MySqlGenerator.php
- Patterns to follow: Implements SqlGeneratorInterface
- Uses backticks for identifiers, MySQL data types

## Requirements (Test Descriptions)
- [ ] `it implements SqlGeneratorInterface`
- [ ] `it generates CREATE TABLE with all column definitions`
- [ ] `it generates DROP TABLE statements`
- [ ] `it generates ALTER TABLE ADD COLUMN`
- [ ] `it generates ALTER TABLE DROP COLUMN`
- [ ] `it generates ALTER TABLE MODIFY COLUMN for type changes`
- [ ] `it generates CREATE INDEX statements`
- [ ] `it generates DROP INDEX statements`
- [ ] `it generates ALTER TABLE ADD CONSTRAINT for foreign keys`
- [ ] `it generates ALTER TABLE DROP FOREIGN KEY`
- [ ] `it maps Column types to MySQL data types`
- [ ] `it handles AUTO_INCREMENT for serial columns`
- [ ] `it generates proper DEFAULT expressions`
- [ ] `it generates down SQL that reverses up SQL`

## Acceptance Criteria
- All requirements have passing tests
- Generated SQL is valid MySQL syntax
- Type mapping covers common types
- DOWN migrations properly reverse UP migrations

## Implementation Notes
(Left blank - filled in by programmer during implementation)
