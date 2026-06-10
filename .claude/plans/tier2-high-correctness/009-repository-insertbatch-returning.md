# Task 009: F9 — Repository::insertBatch RETURNING-based PK assignment + connection dialect accessor

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`Repository::insertBatch()` populates auto-increment primary keys by treating
`lastInsertId()` as the FIRST inserted id and assigning `$firstId + $offset` to each
entity. On PostgreSQL `lastInsertId()` resolves to `LASTVAL()` = the LAST inserted
row's id, so every entity is assigned an id shifted by `count - 1` — silent data
corruption. Use `INSERT ... RETURNING <pk>` on PostgreSQL to map ids correctly; keep
the `LAST_INSERT_ID() + offset` strategy on MySQL (where consecutive auto-increment
for a single multi-row insert is guaranteed). This requires a dialect signal, which
`ConnectionInterface` does not currently expose.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/database/src/Repository/Repository.php`
    (`insertBatch()` ~265 builds the multi-row INSERT; ~343-353 the buggy PK loop:
    `$firstId = $this->connection->lastInsertId();` then `$property->setValue($entity,
    $firstId + $offset)` for each entity)
  - `/Users/markshust/Sites/marko/packages/database/src/Connection/ConnectionInterface.php`
    (today: `connect/disconnect/isConnected/query/execute/prepare/lastInsertId` — NO
    driver/dialect accessor)
  - MySQL + PgSQL driver connection implementations
    (`/Users/markshust/Sites/marko/packages/database-mysql/src/Connection/MySqlConnection.php`,
    `/Users/markshust/Sites/marko/packages/database-pgsql/src/Connection/PgSqlConnection.php`)
    and `/Users/markshust/Sites/marko/packages/database-readwrite/src/Connection/ReadWriteConnection.php`
    (must passthrough the new accessor to `$this->write`)
  - `/Users/markshust/Sites/marko/packages/database/src/Repository/RepositoryInterface.php`
    (docblock already mentions pgsql uses `INSERT … RETURNING`)
- **Interface-ripple scope (CRITICAL — enumerate and update ALL in this cycle).**
  Adding a method to `ConnectionInterface` is an abstract-method addition: every class
  that `implements ConnectionInterface` becomes fatal at load time until it declares
  the method. The ripple is NOT limited to the database packages — it crosses into
  `search`, `session-database`, `queue-database`, `health`, and `admin-auth` test
  stubs. There are ~43 implementers (3 production: `MySqlConnection`, `PgSqlConnection`,
  `ReadWriteConnection`; the rest are anonymous/named test stubs). Every one must gain
  the new method in this task or the whole suite fails to load. Known stub-bearing files
  to update (verify with a fresh `implements ConnectionInterface` grep before finishing):
  `packages/database/tests/Repository/*Test.php`,
  `packages/database/tests/Feature/*.php` + `Helpers.php`,
  `packages/database/tests/Command/Helpers.php`,
  `packages/database/tests/Migration/Helpers.php`,
  `packages/database/tests/Query/SpecEagerLoadCompositionTest.php`,
  `packages/database/tests/Integration/TableExtensionIntegrationTest.php`,
  `packages/database/src/Testing/DatabaseTestHelper.php`,
  `packages/database-readwrite/tests/**`,
  `packages/database-mysql/tests/**`,
  `packages/database-pgsql/tests/**` (incl. `Query/MockConnection.php`),
  `packages/search/tests/Driver/DatabaseSearchDriverTest.php`,
  `packages/session-database/tests/Unit/DatabaseSessionHandlerTest.php`,
  `packages/queue-database/tests/DatabaseQueueTest.php`,
  `packages/queue-database/tests/DatabaseFailedJobRepositoryTest.php`,
  `packages/health/tests/Unit/DatabaseHealthCheckTest.php`,
  `packages/admin-auth/tests/Unit/Repository/*Test.php`,
  `packages/admin-auth/tests/Migration/MigrationTest.php`.
- **Driver-name source.** Do NOT use `PDO::getAttribute(PDO::ATTR_DRIVER_NAME)` (needs a
  live connection). Return a per-driver constant: `MySqlConnection::driverName()` => `'mysql'`,
  `PgSqlConnection::driverName()` => `'pgsql'`, `ReadWriteConnection::driverName()` =>
  `$this->write->driverName()`. Test stubs return a representative literal (`'sqlite'`
  or whatever the stub simulates) — most existing tests use the MySQL `LAST_INSERT_ID`
  path, so a non-`pgsql` literal preserves current behaviour.
- Patterns to follow:
  - Add a minimal dialect accessor `driverName(): string` to `ConnectionInterface`,
    implement in each driver as a constant, passthrough in `ReadWriteConnection`, and add
    representative impls to EVERY test stub above so the suite still loads.
  - pgsql path: append `RETURNING <pk>` to the batch INSERT, execute via the row-
    returning path (`query()`, which on pgsql `fetchAll`s the RETURNING rows), map
    returned ids positionally to `$entities` in row order. Coordinate with Task 008's
    routing (009 does not depend on 008; if both land, the RETURNING write must reach
    the primary — Task 008 routes leading-INSERT `query()` to the write side).
  - No hardcoded fallbacks; throw a loud `BatchInsertException` if the pk column is
    missing from the RETURNING result set, or if the RETURNING row count does not match
    `count($entities)`.

## Requirements (Test Descriptions)
- [ ] `it assigns each entity its true database id when batch-inserting two or more
      entities on a postgresql connection (ids are not shifted by count-1)`
- [ ] `it assigns consecutive ids correctly when batch-inserting on a mysql connection`
- [ ] `it builds an INSERT ... RETURNING <primaryKey> statement on a postgresql connection`
- [ ] `it builds a plain multi-row INSERT (no RETURNING) and uses LAST_INSERT_ID offset on mysql`
- [ ] `it exposes the connection driver name via the ConnectionInterface accessor`
- [ ] `it returns the true ids for three or more entities on pgsql (not just two), proving
      positional mapping over the full RETURNING result set`
- [ ] `it throws a loud BatchInsertException when the pgsql RETURNING result count does not
      match the number of entities`
- [ ] `it routes the RETURNING write through the write connection (not a replica) on a
      read/write connection`
- [ ] `the full test suite still loads after the ConnectionInterface method is added (every
      stub implementer across database/database-*/search/session-database/queue-database/
      health/admin-auth declares driverName())`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
