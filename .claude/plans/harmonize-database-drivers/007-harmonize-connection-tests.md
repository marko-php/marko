# Task 007: Harmonize Connection Test Approach

**Status**: pending
**Depends on**: 003, 006
**Retry count**: 0

## Description
Update PgSqlConnectionTest to use the MySQL testing approach (anonymous classes extending the connection) instead of reflection-based testing.

## Context
- Related files:
  - `packages/database-pgsql/tests/Connection/PgSqlConnectionTest.php`
  - `packages/database-mysql/tests/Connection/MySqlConnectionTest.php` (reference)
- Pattern: MySQL uses anonymous classes that override `createPdo()` to inject test PDO

## Requirements (Test Descriptions)
- [ ] `it replaces reflection-based DSN testing with getDsn() public method call`
- [ ] `it replaces reflection-based PDO injection with anonymous class extending PgSqlConnection`
- [ ] `it removes reflection usage for private method testing`
- [ ] `it ensures all tests use the createPdo() override pattern`

## Acceptance Criteria
- All requirements have passing tests
- PgSqlConnectionTest follows the same pattern as MySqlConnectionTest
- No reflection used for testing connection internals
- All existing tests pass

## Implementation Notes
(Left blank - filled in by programmer during implementation)
