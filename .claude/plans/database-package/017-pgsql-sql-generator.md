# Task 017: PostgreSQL SQL Generator

**Status**: completed
**Depends on**: 011, 015
**Retry count**: 0

## Description
Implement the PostgreSQL-specific SQL generator that produces PostgreSQL DDL statements from schema diffs. This handles PostgreSQL's specific syntax including SERIAL, identity columns, and proper quoting.

## Context
- Related files: packages/database-pgsql/src/Sql/PgSqlGenerator.php
- Patterns to follow: Implements SqlGeneratorInterface
- Uses double quotes for identifiers, PostgreSQL data types

## Requirements (Test Descriptions)
- [x] `it implements SqlGeneratorInterface`
- [x] `it generates CREATE TABLE with all column definitions`
- [x] `it generates DROP TABLE statements`
- [x] `it generates ALTER TABLE ADD COLUMN`
- [x] `it generates ALTER TABLE DROP COLUMN`
- [x] `it generates ALTER TABLE ALTER COLUMN for type changes`
- [x] `it generates CREATE INDEX statements`
- [x] `it generates DROP INDEX statements`
- [x] `it generates ALTER TABLE ADD CONSTRAINT for foreign keys`
- [x] `it generates ALTER TABLE DROP CONSTRAINT for foreign keys`
- [x] `it maps Column types to PostgreSQL data types`
- [x] `it handles SERIAL for auto-increment columns`
- [x] `it generates proper DEFAULT expressions`
- [x] `it generates down SQL that reverses up SQL`

## Acceptance Criteria
- All requirements have passing tests
- Generated SQL is valid PostgreSQL syntax
- Type mapping covers common types
- DOWN migrations properly reverse UP migrations

## Implementation Notes
Implemented `PgSqlGenerator` class at `packages/database-pgsql/src/Sql/PgSqlGenerator.php` with:

- Complete implementation of `SqlGeneratorInterface`
- Type mapping from abstract Column types to PostgreSQL types (INTEGER, BIGINT, VARCHAR, TEXT, BOOLEAN, TIMESTAMP, DATE, TIME, DECIMAL, REAL, DOUBLE PRECISION, JSONB, UUID, BYTEA)
- SERIAL/BIGSERIAL/SMALLSERIAL for auto-increment columns
- Double-quoted identifier quoting per PostgreSQL standards
- Full support for:
  - CREATE TABLE with columns, constraints, and defaults
  - DROP TABLE
  - ALTER TABLE ADD/DROP COLUMN
  - ALTER TABLE ALTER COLUMN (TYPE, SET/DROP NOT NULL, SET/DROP DEFAULT)
  - CREATE INDEX (with UNIQUE support)
  - DROP INDEX
  - ADD/DROP CONSTRAINT for foreign keys
- Up/Down migration generation from SchemaDiff objects
- Multi-column index and foreign key support

Test file: `packages/database-pgsql/tests/Sql/PgSqlGeneratorTest.php` (21 tests, 55 assertions)
