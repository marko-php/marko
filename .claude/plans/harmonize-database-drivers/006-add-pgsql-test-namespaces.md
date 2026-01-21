# Task 006: Add Namespaces to PostgreSQL Tests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add proper PSR-4 namespaces to all PostgreSQL test files to match the MySQL test convention.

## Context
- Related files:
  - `packages/database-pgsql/tests/Connection/PgSqlConnectionTest.php`
  - `packages/database-pgsql/tests/Query/PgSqlQueryBuilderTest.php`
  - `packages/database-pgsql/tests/Sql/PgSqlGeneratorTest.php`
  - `packages/database-pgsql/tests/Introspection/PgSqlIntrospectorTest.php`
  - `packages/database-pgsql/tests/Feature/PgSqlIntegrationTest.php`
- Pattern: MySQL tests use `namespace Marko\Database\MySql\Tests\{Directory};`

## Requirements (Test Descriptions)
- [ ] `it adds namespace Marko\Database\PgSql\Tests\Connection to PgSqlConnectionTest`
- [ ] `it adds namespace Marko\Database\PgSql\Tests\Query to PgSqlQueryBuilderTest`
- [ ] `it adds namespace Marko\Database\PgSql\Tests\Sql to PgSqlGeneratorTest`
- [ ] `it adds namespace Marko\Database\PgSql\Tests\Introspection to PgSqlIntrospectorTest`
- [ ] `it adds namespace Marko\Database\PgSql\Tests\Feature to PgSqlIntegrationTest`

## Acceptance Criteria
- All requirements have passing tests
- All PostgreSQL test files have proper namespaces
- Autoloading works correctly
- All existing tests pass

## Implementation Notes
(Left blank - filled in by programmer during implementation)
