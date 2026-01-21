# Task 002: Harmonize Generator Method Naming

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Standardize method naming in MySqlGenerator to use `generateTableAlterations()` instead of `generateTableAlterStatements()`, matching the PostgreSQL pattern.

## Context
- Related files:
  - `packages/database-mysql/src/Sql/MySqlGenerator.php`
  - `packages/database-pgsql/src/Sql/PgSqlGenerator.php` (reference)
- Patterns to follow: PostgreSQL's `generateTableAlterations()` and `generateReverseTableAlterations()`

## Requirements (Test Descriptions)
- [ ] `it renames generateTableAlterStatements to generateTableAlterations in MySqlGenerator`
- [ ] `it renames generateReverseTableAlterStatements to generateReverseTableAlterations in MySqlGenerator`
- [ ] `it updates all internal calls to use new method names`

## Acceptance Criteria
- All requirements have passing tests
- MySqlGenerator method names match PgSqlGenerator pattern
- All existing tests pass

## Implementation Notes
(Left blank - filled in by programmer during implementation)
