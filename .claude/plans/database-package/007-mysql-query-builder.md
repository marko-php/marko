# Task 007: MySQL Query Builder

**Status**: completed
**Depends on**: 003, 006
**Retry count**: 0

## Description
Implement the MySQL-specific query builder that generates MySQL-compatible SQL. This handles MySQL's specific syntax for identifiers, string escaping, and SQL generation.

## Context
- Related files: packages/database-mysql/src/Query/MySqlQueryBuilder.php
- Patterns to follow: Implements QueryBuilderInterface
- Uses backticks for identifier quoting

## Requirements (Test Descriptions)
- [x] `it implements QueryBuilderInterface`
- [x] `it quotes identifiers with backticks`
- [x] `it builds SELECT queries with column selection`
- [x] `it builds WHERE clauses with parameter binding`
- [x] `it builds WHERE IN clauses correctly`
- [x] `it builds JOIN clauses with proper syntax`
- [x] `it builds ORDER BY with ASC/DESC`
- [x] `it builds LIMIT and OFFSET clauses`
- [x] `it builds INSERT statements with parameter binding`
- [x] `it builds UPDATE statements with WHERE clause`
- [x] `it builds DELETE statements with WHERE clause`
- [x] `it returns last insert ID after insert`
- [x] `it returns affected row count after update/delete`
- [x] `it executes raw queries with parameter binding`

## Acceptance Criteria
- All requirements have passing tests
- SQL is properly parameterized (no SQL injection)
- MySQL-specific syntax is correct
- Integrates with MySqlConnection

## Implementation Notes
- Created MySqlQueryBuilder at packages/database-mysql/src/Query/MySqlQueryBuilder.php
- Added lastInsertId() method to ConnectionInterface and MySqlConnection to support returning insert IDs
- Query builder uses backticks for MySQL identifier quoting (handles table.column format)
- All SQL is parameterized using ? placeholders to prevent SQL injection
- Supports fluent interface with method chaining for building queries
- Tests use SQLite in-memory database via testable connection subclass pattern
