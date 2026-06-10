# Task 020: pgsql insert() returns the actual primary key, not a hardcoded "id"

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`PgSqlQueryBuilder::insert()` always appends `RETURNING "id"` and reads `$result[0]['id']`, so it returns a wrong/zero value for any table whose primary key is not named `id`. The entity layer supports `#[Column(primaryKey: true)]` on any property (e.g. `uuid`, `user_id`), so a non-`id` PK silently yields `0` from `insert()`. Fix: emit `RETURNING <pk>` for the table's actual primary-key column and read that key back.

## Context
- Related files:
  - `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (`insert()` lines 451-480 — builds `INSERT ... RETURNING %s` with `$this->quoteIdentifier('id')` at 474 and returns `(int) ($result[0]['id'] ?? 0)` at 479; `update()` begins at 482)
- Patterns to follow:
  - Return the actual generated key for the table's PK column. The builder must learn the PK column name (the same signal the entity layer uses for `#[Column(primaryKey: true)]`), not assume `'id'`.
  - Quote the PK identifier via the existing `quoteIdentifier()` and read it back from the result row by that column name.
  - No silent `?? 0` masking a wrong column — if the RETURNING row lacks the expected PK key that is a loud error, not a `0`.

### COORDINATE with Tier 2 Task 009 (do NOT add a competing mechanism)
Tier 2 Task 009 (F9 `insertBatch`) adds a `RETURNING`-aware path AND a driver-name/dialect accessor (`driverName()`) to `ConnectionInterface`. If Task 009 already introduces a PK-aware RETURNING helper (a way for the builder to know the PK column), THIS task MUST CONSUME that helper rather than inventing a second PK-resolution path. The two must converge on one mechanism for "what is this table's primary key column."
- **Rebase dependency:** rebase this task onto merged Tier 2 Task 009 before implementation. If implementing first, use the exact helper/signature Tier 2 Task 009 specifies so single-row `insert()` and batch `insertBatch()` share it.
- This is a cross-plan ordering concern, not an in-plan task dependency — `Depends on: [none]`.

### Verification note (read at planning time)
Confirmed against source: `insert()` at 451-480 hardcodes `$this->quoteIdentifier('id')` (line 474) in the RETURNING clause and reads `$result[0]['id']` (479). (Original cited ~469-475 — actual RETURNING line is 470-475; close.)

## Requirements (Test Descriptions)
- [ ] `it returns the generated key for a table whose primary key is not named id`
- [ ] `it emits a RETURNING clause naming the table primary-key column`
- [ ] `it still returns the generated id for a table whose primary key is id`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
