# Task 001: Harmonize QueryBuilder Method Naming

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Standardize method naming in MySqlQueryBuilder to match PgSqlQueryBuilder conventions. This includes renaming private builder methods to use `*Clause` suffix instead of `*Sql` suffix, and aligning implementation patterns.

## Context
- Related files:
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php`
  - `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (reference)
- Patterns to follow: PostgreSQL's `buildWhereClause()`, `buildJoinClause()` etc.

## Requirements (Test Descriptions)
- [ ] `it renames buildWhereSql to buildWhereClause in MySqlQueryBuilder`
- [ ] `it renames buildJoinsSql to buildJoinClause in MySqlQueryBuilder`
- [ ] `it renames buildOrderBySql to buildOrderByClause in MySqlQueryBuilder`
- [ ] `it renames buildLimitOffsetSql to buildLimitOffsetClause in MySqlQueryBuilder`
- [ ] `it changes quoteIdentifier visibility from public to protected in MySqlQueryBuilder`
- [ ] `it changes first() to use chainable limit(1) instead of direct property assignment`
- [ ] `it changes count() to use count alias instead of aggregate`
- [ ] `it adds direction validation to PgSqlQueryBuilder orderBy() method`

## Acceptance Criteria
- All requirements have passing tests
- MySqlQueryBuilder method names match PgSqlQueryBuilder pattern
- Both QueryBuilders use identical naming conventions
- All existing tests pass

## Implementation Notes
(Left blank - filled in by programmer during implementation)
