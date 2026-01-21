# Task 008: PostgreSQL Query Builder

**Status**: completed
**Depends on**: 004, 006
**Retry count**: 0

## Description
Implement the PostgreSQL-specific query builder that generates PostgreSQL-compatible SQL. This handles PostgreSQL's specific syntax for identifiers, RETURNING clause, and SQL generation.

## Context
- Related files: packages/database-pgsql/src/Query/PgSqlQueryBuilder.php
- Patterns to follow: Implements QueryBuilderInterface
- Uses double quotes for identifier quoting

## Requirements (Test Descriptions)
- [x] `it implements QueryBuilderInterface`
- [x] `it quotes identifiers with double quotes`
- [x] `it builds SELECT queries with column selection`
- [x] `it builds WHERE clauses with parameter binding`
- [x] `it builds WHERE IN clauses correctly`
- [x] `it builds JOIN clauses with proper syntax`
- [x] `it builds ORDER BY with ASC/DESC`
- [x] `it builds LIMIT and OFFSET clauses`
- [x] `it builds INSERT statements with RETURNING id`
- [x] `it builds UPDATE statements with WHERE clause`
- [x] `it builds DELETE statements with WHERE clause`
- [x] `it returns last insert ID using RETURNING`
- [x] `it returns affected row count after update/delete`
- [x] `it executes raw queries with parameter binding`

## Acceptance Criteria
- All requirements have passing tests
- SQL is properly parameterized (no SQL injection)
- PostgreSQL-specific syntax is correct (RETURNING, double quotes)
- Integrates with PgSqlConnection

## Implementation Notes
- Created PgSqlQueryBuilder class implementing QueryBuilderInterface
- Uses PostgreSQL-style $1, $2, etc. positional parameters for safe SQL injection prevention
- Uses double quotes for identifier quoting (PostgreSQL standard)
- INSERT uses RETURNING "id" clause for getting last insert ID (PostgreSQL-specific)
- Supports fluent chaining for all query building methods
- Handles table.column dot notation for JOINs
- Added lastInsertId() method to PgSqlConnection to satisfy ConnectionInterface
