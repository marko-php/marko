# Task 007: MySQL Query Builder

**Status**: pending
**Depends on**: 003, 006
**Retry count**: 0

## Description
Implement the MySQL-specific query builder that generates MySQL-compatible SQL. This handles MySQL's specific syntax for identifiers, string escaping, and SQL generation.

## Context
- Related files: packages/database-mysql/src/Query/MySqlQueryBuilder.php
- Patterns to follow: Implements QueryBuilderInterface
- Uses backticks for identifier quoting

## Requirements (Test Descriptions)
- [ ] `it implements QueryBuilderInterface`
- [ ] `it quotes identifiers with backticks`
- [ ] `it builds SELECT queries with column selection`
- [ ] `it builds WHERE clauses with parameter binding`
- [ ] `it builds WHERE IN clauses correctly`
- [ ] `it builds JOIN clauses with proper syntax`
- [ ] `it builds ORDER BY with ASC/DESC`
- [ ] `it builds LIMIT and OFFSET clauses`
- [ ] `it builds INSERT statements with parameter binding`
- [ ] `it builds UPDATE statements with WHERE clause`
- [ ] `it builds DELETE statements with WHERE clause`
- [ ] `it returns last insert ID after insert`
- [ ] `it returns affected row count after update/delete`
- [ ] `it executes raw queries with parameter binding`

## Acceptance Criteria
- All requirements have passing tests
- SQL is properly parameterized (no SQL injection)
- MySQL-specific syntax is correct
- Integrates with MySqlConnection

## Implementation Notes
(Left blank - filled in by programmer during implementation)
