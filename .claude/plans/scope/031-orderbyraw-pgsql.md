# Task 031: Implement `orderByRaw` on `PgSqlQueryBuilder`

**Status**: complete
**Depends on**: 029

## Description
Implement `orderByRaw(string $expression, string $direction): static` on `Marko\Database\PgSql\Query\PgSqlQueryBuilder`. Sibling of task 030 — identical signature and semantics, PG-specific implementation.

## Context
- Related files: `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php`
- Patterns to follow: Mirror task 030 exactly per sibling-modules.md.

## Requirements (Test Descriptions)
- [x] `it appends a raw expression to the order clause without quoting`
- [x] `it rejects expressions containing semicolons`
- [x] `it rejects expressions containing SQL comments (-- or /*)`
- [x] `it preserves direction asc or desc on the emitted ORDER BY`
- [x] `it composes correctly with a regular orderBy call before or after`
- [x] `it emits a single ORDER BY clause with comma-separated entries for mixed regular and raw orders`

## Acceptance Criteria
- Method signature identical to `MySqlQueryBuilder::orderByRaw`.
- `buildOrderByClause()` rendering symmetrical with the MySQL implementation.
- Dangerous-pattern rejection identical to MySQL sibling.

## Implementation Notes
- Task 029 had already added the full `orderByRaw` implementation to `PgSqlQueryBuilder` (method, `$rawOrders` array, and `buildOrderByClause()` integration). All 6 tests passed immediately without any code changes.
- Tests added in `packages/database-pgsql/tests/Query/PgSqlQueryBuilderOrderByRawTest.php`.
- Regular orders are rendered before raw orders in the ORDER BY clause (both arrays merged in that order).
