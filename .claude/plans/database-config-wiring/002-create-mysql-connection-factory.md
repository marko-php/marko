# Task 002: Create MySqlConnectionFactory

**Status**: pending
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
- [ ] `it receives DatabaseConfig via constructor injection`
- [ ] `it creates MySqlConnection with host from config`
- [ ] `it creates MySqlConnection with port from config`
- [ ] `it creates MySqlConnection with database from config`
- [ ] `it creates MySqlConnection with username from config`
- [ ] `it creates MySqlConnection with password from config`
- [ ] `it returns ConnectionInterface from create method`

## Acceptance Criteria
- All requirements have passing tests
- Factory uses constructor property promotion
- Factory follows code standards
- create() method returns MySqlConnection instance

## Implementation Notes
(Left blank - filled in by programmer during implementation)
