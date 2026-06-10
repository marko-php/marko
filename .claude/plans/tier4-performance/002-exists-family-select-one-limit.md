# Task 002: F1 — `SELECT 1 ... LIMIT 1` for `exists` / `existsBy` / `isColumnUnique`

**Status**: pending
**Depends on**: [001]
**Retry count**: 0

## Description
The existence-check family does far more work than needed: `exists($id)` calls `find()` which hydrates a full entity and eager-loads relationships; `existsBy` calls `findOneBy` (full fetch); `isColumnUnique` runs `SELECT *` with no `LIMIT`. All three only need to know whether a row exists. Replace each with a boolean probe — `SELECT 1 FROM <table> WHERE ... LIMIT 1` — returning early without hydration or eager loading.

## Description (scope detail)
This depends on 001 because both edit the same `Repository.php` lookup region; sequencing avoids merge churn. `exists`/`existsBy` may keep delegating only if the delegate is itself a `LIMIT 1` probe — prefer dedicated `SELECT 1 ... LIMIT 1` SQL so no entity is ever hydrated for a bool.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php` (`exists` ~517-521, `existsBy` ~528-532, `isColumnUnique` ~542-557)
  - `packages/database/tests/Feature/RepositoryCrudTest.php` (anonymous `ConnectionInterface` stub query-recording pattern)
- Patterns to follow:
  - `exists($id)`: `SELECT 1 FROM <table> WHERE <pkColumn> = ? LIMIT 1`, return `count($rows) > 0`.
  - `existsBy(array $criteria)`: build WHERE from the same property->column map as `findBy` (multiple criteria joined with ` AND `), `SELECT 1 ... LIMIT 1`, return `count($rows) > 0`. Preserve the existing multi-criteria behavior of `findBy` exactly — same property->column resolution, same positional binding order.
  - `isColumnUnique($column, $value, $excludeId)`: `SELECT 1 FROM <table> WHERE <column> = ?` plus optional `AND <pkColumn> != ?`, append `LIMIT 1`, return `count($rows) === 0`. With `LIMIT 1` the probe returns at most one row, so `=== 0` correctly means "no other row holds the value" — identical semantics to the prior unbounded `SELECT *`.
  - No `hydrate()`, no `eagerLoadRelationships()` on any of these paths — the probe must never construct an entity.
  - Equivalence guard: each test must assert the boolean result matches the pre-change behavior for both the present and absent cases, not merely that `LIMIT 1`/`SELECT 1` appears in the SQL.

## Requirements (Test Descriptions)
- [ ] `it returns true from exists when a row with the id is present`
- [ ] `it returns false from exists when no row has the id`
- [ ] `it probes exists with SELECT 1 and LIMIT 1 and does not hydrate an entity`
- [ ] `it returns true from existsBy when criteria match a row`
- [ ] `it returns false from existsBy when criteria match nothing`
- [ ] `it probes existsBy with SELECT 1 and LIMIT 1`
- [ ] `it returns true from isColumnUnique when no other row holds the value`
- [ ] `it returns false from isColumnUnique when another row holds the value`
- [ ] `it excludes the given id from the isColumnUnique uniqueness check`
- [ ] `it probes isColumnUnique with SELECT 1 and LIMIT 1 instead of SELECT star`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
