# Task 002: Create MySqlConnectionFactory

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a factory class in marko/database-mysql that receives DatabaseConfig via DI and creates MySqlConnection instances with the config values.

## Context
- Related files:
  - `packages/database-mysql/src/Factory/MySqlConnectionFactory.php` (create)
  - `packages/database-mysql/tests/Factory/MySqlConnectionFactoryTest.php` (create)
  - `packages/database/src/Config/DatabaseConfig.php` (reference)
  - `packages/database-mysql/src/Connection/MySqlConnection.php` (reference)
- Patterns to follow: Factory pattern, constructor injection

## Requirements (Test Descriptions)
- [x] `it receives DatabaseConfig via constructor injection`
- [x] `it creates MySqlConnection with host from config`
- [x] `it creates MySqlConnection with port from config`
- [x] `it creates MySqlConnection with database from config`
- [x] `it creates MySqlConnection with username from config`
- [x] `it creates MySqlConnection with password from config`
- [x] `it returns ConnectionInterface from create method`

## Acceptance Criteria
- All requirements have passing tests
- Factory uses constructor property promotion
- Factory follows code standards
- create() method returns MySqlConnection instance

## Implementation Notes
(Left blank - filled in by programmer during implementation)
