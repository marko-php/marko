# Task 009: Database Introspector Interface

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Define the IntrospectorInterface for reading the current database schema. Introspectors query information_schema to understand existing tables, columns, indexes, and foreign keys for comparison with entity-defined schema.

## Context
- Related files: packages/database/src/Introspection/IntrospectorInterface.php
- Patterns to follow: Interface pattern, returns Schema value objects
- Used by diff engine to compare actual DB vs entity-defined schema

## Requirements (Test Descriptions)
- [x] `it defines getTables() returning array of table names`
- [x] `it defines getTable(name) returning Table value object or null`
- [x] `it defines tableExists(name) returning boolean`
- [x] `it defines getColumns(table) returning array of Column value objects`
- [x] `it defines getIndexes(table) returning array of Index value objects`
- [x] `it defines getForeignKeys(table) returning array of ForeignKey value objects`
- [x] `it defines getPrimaryKey(table) returning column names`

## Acceptance Criteria
- All requirements have passing tests
- Interface returns Schema value objects (from Task 005)
- Methods are minimal and focused
- No driver-specific code

## Implementation Notes
Created IntrospectorInterface with 7 methods for database schema introspection:
- `getTables()`: Returns array of table names
- `getTable(name)`: Returns Table value object or null
- `tableExists(name)`: Returns boolean
- `getColumns(table)`: Returns array of Column value objects
- `getIndexes(table)`: Returns array of Index value objects
- `getForeignKeys(table)`: Returns array of ForeignKey value objects
- `getPrimaryKey(table)`: Returns array of primary key column names

Interface uses Schema value objects from Task 005 (Table, Column, Index, ForeignKey).
