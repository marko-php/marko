# Task 012: Integration Test Over pgsql Wiring

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Integration test that proves the full readwrite wiring works end-to-end when the underlying driver is `marko/database-pgsql`. Constructs the real boot pipeline (real `BindingRegistry`, real `PgSqlConnectionFactory`, real `ReadWriteConnectionConfig` loading a fixture config file, real `ReadWriteConnection`), but uses a stubbed `ConnectionFactoryInterface` OR stubbed `PgSqlConnection` so no real database is touched.

## Context
- **File location:** `packages/database-readwrite/tests/Integration/PgSqlWiringTest.php`
- **What "integration" means here:** End-to-end through the boot pipeline, but with mocked PDO/connection so the test never connects to a real database. Mirror the technique used in `packages/database-pgsql/tests/Module/DialectOverrideTest.php` (the proven 2-module-scenario fixture from #31).
- **Test scenarios:**
  - **Scenario A:** Load `marko/database-pgsql` manifest + `marko/database-readwrite` manifest into `BindingRegistry`, run boot callbacks in order. After boot, resolve `ConnectionInterface` — assert it returns a `ReadWriteConnection` instance, NOT a `PgSqlConnection`.
  - **Scenario B:** After boot, the resolved `ConnectionInterface->query()` routes to a stubbed replica (asserted via instrumented stub). The `execute()` routes to a stubbed writer.
  - **Scenario C:** Beginning a transaction routes to the writer's transaction methods.
  - **Scenario D:** The config file uses `driver: 'pgsql'` per connection (write and each read) — assert `PgSqlConnectionFactory::make()` is called with each `DatabaseConfig`.
- **Replacing PgSqlConnectionFactory with a stub:** Either bind a stub factory in a third fixture module that runs after pgsql's module, OR override the factory directly in the test setup via `$container->bind(ConnectionFactoryInterface::class, $stubFactory)`. The boot callback pattern from task 011 will pick up the override.
- **Fixture connections:** The stub `ConnectionInterface & TransactionInterface` implementations from tasks 007-010 can be reused. Place reusable fixtures under `packages/database-readwrite/tests/Fixtures/`.
- **Fixture config file:** Generate a temporary `config/database.php` with the nested shape, mirroring how `packages/database-pgsql/tests/Module/ModuleBindingsTest.php` uses temp directories for config.

## Requirements (Test Descriptions)
- [ ] `it resolves ConnectionInterface to a ReadWriteConnection after the readwrite boot runs over pgsql`
- [ ] `it routes query() through the readwrite connection to a stubbed pgsql replica`
- [ ] `it routes execute() through the readwrite connection to the stubbed pgsql writer`
- [ ] `it routes beginTransaction() to the stubbed pgsql writer`
- [ ] `it calls PgSqlConnectionFactory::make() with a DatabaseConfig per configured connection`
- [ ] `it resolves TransactionInterface to the same instance as ConnectionInterface after boot`

## Acceptance Criteria
- All 6 requirements have passing tests.
- No test connects to a real PostgreSQL instance.
- Fixtures are reusable across tasks 012 and 013 where applicable.
- `composer test` passes; no regression in any other package.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
