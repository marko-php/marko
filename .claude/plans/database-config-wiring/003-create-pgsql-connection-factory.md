# Task 003: Create PgSqlConnectionFactory

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a factory class in marko/database-pgsql that receives DatabaseConfig via DI and creates PgSqlConnection instances with the config values. This mirrors MySqlConnectionFactory for sibling consistency.

## Context
- Related files:
  - `packages/database-pgsql/src/Factory/PgSqlConnectionFactory.php` (create)
  - `packages/database-pgsql/tests/Factory/PgSqlConnectionFactoryTest.php` (create)
  - `packages/database/src/Config/DatabaseConfig.php` (reference)
  - `packages/database-pgsql/src/Connection/PgSqlConnection.php` (reference)
- Patterns to follow: MySqlConnectionFactory (sibling consistency), factory pattern

## Requirements (Test Descriptions)
- [x] `it receives DatabaseConfig via constructor injection`
- [x] `it creates PgSqlConnection with host from config`
- [x] `it creates PgSqlConnection with port from config`
- [x] `it creates PgSqlConnection with database from config`
- [x] `it creates PgSqlConnection with username from config`
- [x] `it creates PgSqlConnection with password from config`
- [x] `it returns ConnectionInterface from create method`

## Acceptance Criteria
- All requirements have passing tests
- Factory mirrors MySqlConnectionFactory structure exactly
- Factory uses constructor property promotion
- Factory follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
