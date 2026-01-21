# Task 011: PostgreSQL Introspector

**Status**: pending
**Depends on**: 004, 009
**Retry count**: 0

## Description
Implement the PostgreSQL-specific introspector that reads schema information from PostgreSQL's information_schema and pg_catalog. This converts PostgreSQL metadata into the Schema value objects used by the diff engine.

## Context
- Related files: packages/database-pgsql/src/Introspection/PgSqlIntrospector.php
- Patterns to follow: Implements IntrospectorInterface
- Queries information_schema and pg_catalog for complete information

## Requirements (Test Descriptions)
- [ ] `it implements IntrospectorInterface`
- [ ] `it reads table list from information_schema.tables`
- [ ] `it reads column definitions from information_schema.columns`
- [ ] `it maps PostgreSQL data types to Column value objects`
- [ ] `it detects nullable columns`
- [ ] `it detects default values including sequences`
- [ ] `it detects serial/identity columns`
- [ ] `it reads indexes from pg_indexes`
- [ ] `it detects unique indexes`
- [ ] `it reads foreign keys from information_schema.table_constraints`
- [ ] `it detects ON DELETE and ON UPDATE actions`
- [ ] `it filters to public schema by default`

## Acceptance Criteria
- All requirements have passing tests
- Type mapping is accurate for common PostgreSQL types
- Returns Schema value objects (from Task 005)
- Handles serial vs identity columns properly

## Implementation Notes
(Left blank - filled in by programmer during implementation)
