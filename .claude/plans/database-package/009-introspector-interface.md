# Task 009: Database Introspector Interface

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Define the IntrospectorInterface for reading the current database schema. Introspectors query information_schema to understand existing tables, columns, indexes, and foreign keys for comparison with entity-defined schema.

## Context
- Related files: packages/database/src/Introspection/IntrospectorInterface.php
- Patterns to follow: Interface pattern, returns Schema value objects
- Used by diff engine to compare actual DB vs entity-defined schema

## Requirements (Test Descriptions)
- [ ] `it defines getTables() returning array of table names`
- [ ] `it defines getTable(name) returning Table value object or null`
- [ ] `it defines tableExists(name) returning boolean`
- [ ] `it defines getColumns(table) returning array of Column value objects`
- [ ] `it defines getIndexes(table) returning array of Index value objects`
- [ ] `it defines getForeignKeys(table) returning array of ForeignKey value objects`
- [ ] `it defines getPrimaryKey(table) returning column names`

## Acceptance Criteria
- All requirements have passing tests
- Interface returns Schema value objects (from Task 005)
- Methods are minimal and focused
- No driver-specific code

## Implementation Notes
(Left blank - filled in by programmer during implementation)
