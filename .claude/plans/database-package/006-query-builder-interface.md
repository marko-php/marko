# Task 006: Query Builder Interface

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Define the QueryBuilderInterface that provides a fluent API for building SQL queries. This interface supports SELECT, INSERT, UPDATE, DELETE operations with where clauses, joins, ordering, and pagination.

## Context
- Related files: packages/database/src/Query/QueryBuilderInterface.php
- Patterns to follow: Fluent builder pattern
- Must support both fluent building and raw query execution

## Requirements (Test Descriptions)
- [x] `it defines table() method to set target table`
- [x] `it defines select() method for column selection`
- [x] `it defines where() method with column, operator, value`
- [x] `it defines whereIn() method for IN clauses`
- [x] `it defines whereNull() and whereNotNull() methods`
- [x] `it defines orWhere() for OR conditions`
- [x] `it defines join(), leftJoin(), rightJoin() methods`
- [x] `it defines orderBy() method with direction`
- [x] `it defines limit() and offset() methods`
- [x] `it defines get() method returning array of rows`
- [x] `it defines first() method returning single row or null`
- [x] `it defines insert() method with data array`
- [x] `it defines update() method with data array`
- [x] `it defines delete() method`
- [x] `it defines count() method returning integer`
- [x] `it defines raw() method for raw SQL with bindings`

## Acceptance Criteria
- All requirements have passing tests
- Interface is driver-agnostic
- Methods return $this for chaining where appropriate
- Clear separation between building and execution

## Implementation Notes
- Created QueryBuilderInterface at packages/database/src/Query/QueryBuilderInterface.php
- Used `static` return type for all fluent methods to support proper chaining in implementations
- Used variadic parameter for select() method to allow flexible column selection
- All where methods support fluent chaining (where, whereIn, whereNull, whereNotNull, orWhere)
- Join methods (join, leftJoin, rightJoin) all take table, first column, operator, and second column
- orderBy() has a default direction of 'ASC'
- Execution methods: get() returns array, first() returns nullable array, insert/update/delete return int
- raw() method allows escape hatch for complex queries with parameter bindings
