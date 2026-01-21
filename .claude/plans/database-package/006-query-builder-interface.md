# Task 006: Query Builder Interface

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Define the QueryBuilderInterface that provides a fluent API for building SQL queries. This interface supports SELECT, INSERT, UPDATE, DELETE operations with where clauses, joins, ordering, and pagination.

## Context
- Related files: packages/database/src/Query/QueryBuilderInterface.php
- Patterns to follow: Fluent builder pattern
- Must support both fluent building and raw query execution

## Requirements (Test Descriptions)
- [ ] `it defines table() method to set target table`
- [ ] `it defines select() method for column selection`
- [ ] `it defines where() method with column, operator, value`
- [ ] `it defines whereIn() method for IN clauses`
- [ ] `it defines whereNull() and whereNotNull() methods`
- [ ] `it defines orWhere() for OR conditions`
- [ ] `it defines join(), leftJoin(), rightJoin() methods`
- [ ] `it defines orderBy() method with direction`
- [ ] `it defines limit() and offset() methods`
- [ ] `it defines get() method returning array of rows`
- [ ] `it defines first() method returning single row or null`
- [ ] `it defines insert() method with data array`
- [ ] `it defines update() method with data array`
- [ ] `it defines delete() method`
- [ ] `it defines count() method returning integer`
- [ ] `it defines raw() method for raw SQL with bindings`

## Acceptance Criteria
- All requirements have passing tests
- Interface is driver-agnostic
- Methods return $this for chaining where appropriate
- Clear separation between building and execution

## Implementation Notes
(Left blank - filled in by programmer during implementation)
