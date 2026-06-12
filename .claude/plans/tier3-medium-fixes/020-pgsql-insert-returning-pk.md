# Task 020: pgsql insert() returns the actual primary key, not a hardcoded "id"

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`PgSqlQueryBuilder::insert()` always appends `RETURNING "id"` and reads `$result[0]['id']`, so it returns a wrong/zero value for any table whose primary key is not named `id`. The entity layer supports `#[Column(primaryKey: true)]` on any property (e.g. `uuid`, `user_id`), so a non-`id` PK silently yields `0` from `insert()`. Fix: emit `RETURNING <pk>` for the table's actual primary-key column and read that key back. Because the fluent builder has no entity metadata (only `$table` + `$data`), the PK column must be passed in via an additive optional `?string $primaryKey = null` parameter (default `'id'`), NOT inferred — see the corrected coordination note below.

## Context
- Related files:
  - `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (`insert()` lines **544-577** — builds `INSERT INTO %s (%s) VALUES (%s) RETURNING %s` with `$this->quoteIdentifier('id')` at line **571** and returns `(int) ($result[0]['id'] ?? 0)` at line **576**; `update()` begins at 582)
  - `packages/database/src/Query/QueryBuilderInterface.php` (`insert(array $data): int` at line 381 — the SHARED contract; mysql builder implements it too)
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php` (`insert()` at ~549 — uses `lastInsertId()`, no RETURNING; needs interface-parity update only)
  - `packages/database-pgsql/src/Exceptions/` (loud-error factory for the missing-PK-key case)
- Patterns to follow:
  - Return the actual generated key for the table's PK column. The builder must learn the PK column name — but it currently has ONLY `$table` + the `$data` array, with no entity metadata, so it cannot infer the PK on its own.
  - Quote the PK identifier via the existing `quoteIdentifier()` and read it back from the result row by that column name.
  - **No silent `?? 0`** masking a wrong column — if the RETURNING row lacks the expected PK key that is a LOUD error (a `MarkoException` subclass with message/context/suggestion), not a `0`.

### How the builder learns the PK (CORRECTED — no Tier 2 helper exists to consume)
**Verified against MERGED source:** `PgSqlQueryBuilder` has NO `insertBatch()` and NO PK-resolution helper. The only Tier 2 Task 009 artifact that landed is `ConnectionInterface::driverName()` (already present). There is nothing to "consume." `Repository::insert()` does not even use `QueryBuilder::insert()` — it builds raw SQL + `lastInsertId()` and has its own RETURNING path for batch inserts. So `QueryBuilder::insert()` is a standalone fluent API whose only PK signal must be added by THIS task.
- **Recommended mechanism (additive, backward-compatible):** add an optional `?string $primaryKey = null` parameter to `insert()` on `QueryBuilderInterface` and both driver builders. When null, default to `'id'` (preserves every existing caller). The pgsql builder emits `RETURNING <quoteIdentifier($primaryKey ?? 'id')>` and reads that column back. The mysql builder accepts the param for interface parity but ignores it (it still uses `lastInsertId()`). Keep the change surgical.
- This is a cross-plan ordering concern only, not an in-plan task dependency — `Depends on: [none]`.

### Verification note (read at planning time — line numbers corrected)
Confirmed against MERGED source: `insert()` is at lines **544-577** (the original cited 451-480 has DRIFTED — that range is now `orderBy`/`offset`). RETURNING hardcodes `$this->quoteIdentifier('id')` at line **571**; the read is `(int) ($result[0]['id'] ?? 0)` at line **576**.

## Requirements (Test Descriptions)
- [x] `it returns the generated key for a table whose primary key is not named id`
- [x] `it emits a RETURNING clause naming the table primary-key column`
- [x] `it still returns the generated id for a table whose primary key is id (default when no primary key is specified)`
- [x] `it throws a loud exception when the RETURNING row lacks the expected primary-key column instead of returning zero`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added optional `?string $primaryKey = null` parameter to `QueryBuilderInterface::insert()`, `PgSqlQueryBuilder::insert()`, and `MySqlQueryBuilder::insert()`
- `PgSqlQueryBuilder::insert()` now uses `$primaryKey ?? 'id'` for `RETURNING <pk>` and reads back `$result[0][$pk]` instead of the hardcoded `$result[0]['id']`
- Created `InsertReturningException` in `packages/database-pgsql/src/Exceptions/` with a `missingPrimaryKeyColumn()` factory method — throws loud error instead of silently returning `0` when the RETURNING row is missing the expected PK column
- `MySqlQueryBuilder::insert()` accepts the parameter for interface parity but ignores it (still uses `lastInsertId()`)
- All 4 requirements have passing tests in `PgSqlQueryBuilderTest.php`
