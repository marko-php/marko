# Task 013: Integration Test Over mysql Wiring

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Mirror of task 012 for the MySQL driver. Proves the readwrite wiring works end-to-end over `marko/database-mysql` with `MySqlConnectionFactory`. Same scenarios, same fixture pattern, just swapping the driver.

## Context
- **File location:** `packages/database-readwrite/tests/Integration/MySqlWiringTest.php`
- **Reuse fixtures from task 012** where possible — the stub `ConnectionInterface & TransactionInterface` implementations should be driver-agnostic.
- **Scenarios** are the same as task 012, swapping `pgsql` → `mysql` and `PgSqlConnectionFactory` → `MySqlConnectionFactory`.
- **Per `.claude/sibling-modules.md`:** This test must read as a mirror of `PgSqlWiringTest` — identical structure, identical naming pattern, only the driver differs. A reviewer flipping between the two files should see them as obvious siblings.

## Requirements (Test Descriptions)
- [ ] `it resolves ConnectionInterface to a ReadWriteConnection after the readwrite boot runs over mysql`
- [ ] `it routes query() through the readwrite connection to a stubbed mysql replica`
- [ ] `it routes execute() through the readwrite connection to the stubbed mysql writer`
- [ ] `it routes beginTransaction() to the stubbed mysql writer`
- [ ] `it calls MySqlConnectionFactory::make() with a DatabaseConfig per configured connection`
- [ ] `it resolves TransactionInterface to the same instance as ConnectionInterface after boot`

## Acceptance Criteria
- All 6 requirements have passing tests.
- File structure exactly mirrors `PgSqlWiringTest.php` (test names, `describe()` blocks, fixture usage — all parallel).
- No test connects to a real MySQL instance.
- `composer test` passes.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
