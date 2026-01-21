# Task 017: PostgreSQL SQL Generator

**Status**: pending
**Depends on**: 011, 015
**Retry count**: 0

## Description
Implement the PostgreSQL-specific SQL generator that produces PostgreSQL DDL statements from schema diffs. This handles PostgreSQL's specific syntax including SERIAL, identity columns, and proper quoting.

## Context
- Related files: packages/database-pgsql/src/Sql/PgSqlGenerator.php
- Patterns to follow: Implements SqlGeneratorInterface
- Uses double quotes for identifiers, PostgreSQL data types

## Requirements (Test Descriptions)
- [ ] `it implements SqlGeneratorInterface`
- [ ] `it generates CREATE TABLE with all column definitions`
- [ ] `it generates DROP TABLE statements`
- [ ] `it generates ALTER TABLE ADD COLUMN`
- [ ] `it generates ALTER TABLE DROP COLUMN`
- [ ] `it generates ALTER TABLE ALTER COLUMN for type changes`
- [ ] `it generates CREATE INDEX statements`
- [ ] `it generates DROP INDEX statements`
- [ ] `it generates ALTER TABLE ADD CONSTRAINT for foreign keys`
- [ ] `it generates ALTER TABLE DROP CONSTRAINT for foreign keys`
- [ ] `it maps Column types to PostgreSQL data types`
- [ ] `it handles SERIAL for auto-increment columns`
- [ ] `it generates proper DEFAULT expressions`
- [ ] `it generates down SQL that reverses up SQL`

## Acceptance Criteria
- All requirements have passing tests
- Generated SQL is valid PostgreSQL syntax
- Type mapping covers common types
- DOWN migrations properly reverse UP migrations

## Implementation Notes
(Left blank - filled in by programmer during implementation)
