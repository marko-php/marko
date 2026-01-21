# Task 008: PostgreSQL Query Builder

**Status**: pending
**Depends on**: 004, 006
**Retry count**: 0

## Description
Implement the PostgreSQL-specific query builder that generates PostgreSQL-compatible SQL. This handles PostgreSQL's specific syntax for identifiers, RETURNING clause, and SQL generation.

## Context
- Related files: packages/database-pgsql/src/Query/PgSqlQueryBuilder.php
- Patterns to follow: Implements QueryBuilderInterface
- Uses double quotes for identifier quoting

## Requirements (Test Descriptions)
- [ ] `it implements QueryBuilderInterface`
- [ ] `it quotes identifiers with double quotes`
- [ ] `it builds SELECT queries with column selection`
- [ ] `it builds WHERE clauses with parameter binding`
- [ ] `it builds WHERE IN clauses correctly`
- [ ] `it builds JOIN clauses with proper syntax`
- [ ] `it builds ORDER BY with ASC/DESC`
- [ ] `it builds LIMIT and OFFSET clauses`
- [ ] `it builds INSERT statements with RETURNING id`
- [ ] `it builds UPDATE statements with WHERE clause`
- [ ] `it builds DELETE statements with WHERE clause`
- [ ] `it returns last insert ID using RETURNING`
- [ ] `it returns affected row count after update/delete`
- [ ] `it executes raw queries with parameter binding`

## Acceptance Criteria
- All requirements have passing tests
- SQL is properly parameterized (no SQL injection)
- PostgreSQL-specific syntax is correct (RETURNING, double quotes)
- Integrates with PgSqlConnection

## Implementation Notes
(Left blank - filled in by programmer during implementation)
