# Task 003: Harmonize Connection Classes

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Align connection patterns between MySqlConnection and PgSqlConnection. Add the `ensureConnected()` abstraction to MySQL and add the testable `createPdo()` pattern to PostgreSQL.

## Context
- Related files:
  - `packages/database-mysql/src/Connection/MySqlConnection.php`
  - `packages/database-pgsql/src/Connection/PgSqlConnection.php`
- Patterns: Both should have `ensureConnected()` for internal use and `createPdo()` for testability

## Requirements (Test Descriptions)
- [ ] `it adds ensureConnected() private method to MySqlConnection`
- [ ] `it changes all direct connect() calls to use ensureConnected() in MySqlConnection`
- [ ] `it adds createPdo() protected method to PgSqlConnection for testability`
- [ ] `it refactors PgSqlConnection connect() to use createPdo()`
- [ ] `it adds public getDsn() method to PgSqlConnection for consistency`
- [ ] `it keeps buildDsn() as private helper in PgSqlConnection`

## Acceptance Criteria
- All requirements have passing tests
- Both connection classes have identical patterns for connection management
- Both classes support test mocking via createPdo() override
- All existing tests pass

## Implementation Notes
(Left blank - filled in by programmer during implementation)
