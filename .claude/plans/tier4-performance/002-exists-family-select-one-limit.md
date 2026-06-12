# Task 002: F1 — `SELECT 1 ... LIMIT 1` for `exists` / `existsBy` / `isColumnUnique`

**Status**: complete
**Depends on**: [001]
**Retry count**: 0

## Description
The existence-check family does far more work than needed: `exists($id)` calls `find()` which hydrates a full entity and eager-loads relationships; `existsBy` calls `findOneBy` (full fetch); `isColumnUnique` runs `SELECT *` with no `LIMIT`. All three only need to know whether a row exists. Replace each with a boolean probe — `SELECT 1 FROM <table> WHERE ... LIMIT 1` — returning early without hydration or eager loading.

## Description (scope detail)
This depends on 001 because both edit the same `Repository.php` lookup region; sequencing avoids merge churn. **In the CURRENT code `exists($id)` delegates to `find()` (line 544) and `existsBy()` delegates to `findOneBy()` (line 555).** After Task 001, `findOneBy` returns a fully hydrated, eager-loaded entity capped at `LIMIT 1` — so if `existsBy`/`exists` keep delegating, they STILL hydrate an entity for a bool, violating this task's "the probe must never construct an entity" requirement. Therefore replace BOTH bodies with dedicated `SELECT 1 ... LIMIT 1` probes; do NOT keep delegating to `find`/`findOneBy`.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php` (`exists` ~541-545, `existsBy` ~552-556, `isColumnUnique` ~561-582 — note `isColumnUnique` is `protected`)
  - `packages/database/tests/Feature/RepositoryCrudTest.php` (anonymous `ConnectionInterface` stub query-recording pattern; stub at line 49 includes a `driverName()` method at line 159 — new stubs MUST include it too)
- Patterns to follow:
  - `exists($id)`: `SELECT 1 FROM <table> WHERE <pkColumn> = ? LIMIT 1`, return `count($rows) > 0`.
  - `existsBy(array $criteria)`: build WHERE from the same property->column map as `findBy` (multiple criteria joined with ` AND `), `SELECT 1 ... LIMIT 1`, return `count($rows) > 0`. Preserve the existing multi-criteria behavior of `findBy` exactly — same property->column resolution, same positional binding order.
  - `isColumnUnique($column, $value, $excludeId)`: `SELECT 1 FROM <table> WHERE <column> = ?` plus optional `AND <pkColumn> != ?`, append `LIMIT 1`, return `count($rows) === 0`. With `LIMIT 1` the probe returns at most one row, so `=== 0` correctly means "no other row holds the value" — identical semantics to the prior unbounded `SELECT *`.
  - No `hydrate()`, no `eagerLoadRelationships()`, and no delegation to `find`/`findOneBy` on any of these paths — the probe must never construct an entity. Verify by asserting the recorded SQL is `SELECT 1 ...` (not `SELECT *`) and that no eager-load query is emitted.
  - Equivalence guard: each test must assert the boolean result matches the pre-change behavior for both the present and absent cases, not merely that `LIMIT 1`/`SELECT 1` appears in the SQL.
  - Any new inline anonymous `ConnectionInterface` stub MUST implement `driverName(): string` (Tier 2 interface addition) or it fatals at instantiation.

## Requirements (Test Descriptions)
- [x] `it returns true from exists when a row with the id is present`
- [x] `it returns false from exists when no row has the id`
- [x] `it probes exists with SELECT 1 and LIMIT 1 and does not hydrate an entity`
- [x] `it returns true from existsBy when criteria match a row`
- [x] `it returns false from existsBy when criteria match nothing`
- [x] `it probes existsBy with SELECT 1 and LIMIT 1`
- [x] `it returns true from isColumnUnique when no other row holds the value`
- [x] `it returns false from isColumnUnique when another row holds the value`
- [x] `it excludes the given id from the isColumnUnique uniqueness check`
- [x] `it probes isColumnUnique with SELECT 1 and LIMIT 1 instead of SELECT star`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Replaced `exists()` body with `SELECT 1 FROM <table> WHERE <pk> = ? LIMIT 1`, returns `count($rows) > 0`
- Replaced `existsBy()` body with same property→column mapping as `findBy`, `SELECT 1 ... LIMIT 1`, returns `count($rows) > 0`
- Replaced `isColumnUnique()` body with `SELECT 1 FROM <table> WHERE <column> = ? [AND <pk> != ?] LIMIT 1`, returns `count($rows) === 0`
- None of the three methods delegate to `find`/`findOneBy` — no hydration, no eager-loading on any probe path
- New test file: `packages/database/tests/Feature/RepositoryExistsTest.php` with 10 tests covering boolean correctness and SQL shape assertions
- Anonymous `ConnectionInterface` stub includes `driverName(): string` as required by Tier 2 interface
