# Task 011: PostgreSQL Introspector

**Status**: completed
**Depends on**: 004, 009
**Retry count**: 0

## Description
Implement the PostgreSQL-specific introspector that reads schema information from PostgreSQL's information_schema and pg_catalog. This converts PostgreSQL metadata into the Schema value objects used by the diff engine.

## Context
- Related files: packages/database-pgsql/src/Introspection/PgSqlIntrospector.php
- Patterns to follow: Implements IntrospectorInterface
- Queries information_schema and pg_catalog for complete information

## Requirements (Test Descriptions)
- [x] `it implements IntrospectorInterface`
- [x] `it reads table list from information_schema.tables`
- [x] `it reads column definitions from information_schema.columns`
- [x] `it maps PostgreSQL data types to Column value objects`
- [x] `it detects nullable columns`
- [x] `it detects default values including sequences`
- [x] `it detects serial/identity columns`
- [x] `it reads indexes from pg_indexes`
- [x] `it detects unique indexes`
- [x] `it reads foreign keys from information_schema.table_constraints`
- [x] `it detects ON DELETE and ON UPDATE actions`
- [x] `it filters to public schema by default`

## Acceptance Criteria
- All requirements have passing tests
- Type mapping is accurate for common PostgreSQL types
- Returns Schema value objects (from Task 005)
- Handles serial vs identity columns properly

## Implementation Notes
Implemented PgSqlIntrospector class with the following features:

1. **Table introspection**: Queries information_schema.tables for BASE TABLE type tables filtered by schema (defaults to 'public')

2. **Column introspection**: Reads from information_schema.columns and enriches with:
   - Primary key detection via pg_constraint (contype = 'p')
   - Unique constraint detection via pg_constraint (contype = 'u')
   - Auto-increment detection for both serial columns (nextval sequences) and identity columns (is_identity = 'YES')

3. **Type mapping**: Comprehensive mapping from PostgreSQL types to normalized types including:
   - integer, bigint, smallint
   - character varying -> varchar, character -> char
   - text, boolean, uuid, json, jsonb
   - timestamp/timestamptz, date, time
   - numeric -> decimal, real -> float, double precision -> double
   - bytea -> blob

4. **Default value parsing**: Handles PostgreSQL-specific defaults:
   - String literals with type casts (e.g., 'active'::character varying)
   - Numeric literals (integer and float)
   - Boolean literals (true/false)
   - Expressions (CURRENT_TIMESTAMP, NOW())
   - Sequence defaults treated as auto_increment rather than regular defaults

5. **Index introspection**: Uses pg_indexes view, parses indexdef to extract:
   - Column names from CREATE INDEX definition
   - Unique vs regular btree indexes

6. **Foreign key introspection**: Joins information_schema.table_constraints with key_column_usage, constraint_column_usage, and referential_constraints to get:
   - Foreign key columns and referenced columns (supports multi-column FKs)
   - ON DELETE and ON UPDATE actions

Additional tests added beyond requirements:
- Composite primary key detection
- Multi-column foreign key handling
- Full table schema retrieval (getTable method)
- Table existence checking
