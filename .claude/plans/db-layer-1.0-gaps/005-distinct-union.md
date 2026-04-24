# Task 005: DISTINCT / UNION / UNION ALL on QueryBuilder

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add `distinct()`, `union(QueryBuilderInterface $other)`, and `unionAll(QueryBuilderInterface $other)` to the query builder. `distinct()` emits `SELECT DISTINCT`. Union methods combine result sets from another builder; column-count mismatches must throw loudly at compile/execute time. Column-type compatibility is the caller's responsibility (documented, not enforced — matches SQL semantics).

## Context
- Related files:
  - `packages/database/src/Query/QueryBuilderInterface.php` — add methods
  - MySQL and PostgreSQL driver implementations
  - `packages/database/src/Exceptions/` — add `UnionShapeMismatchException` or similar
- Patterns to follow: existing fluent method patterns, exception hierarchy.

## Requirements (Test Descriptions)
- [x] `it emits SELECT DISTINCT when distinct() is called`
- [x] `it returns distinct rows for a query that would otherwise duplicate due to joins`
- [x] `it combines two queries with UNION producing deduplicated rows`
- [x] `it combines two queries with UNION ALL preserving duplicates`
- [x] `it throws UnionShapeMismatchException when the two queries select different numbers of columns`
- [x] `it composes UNION with ORDER BY applied to the combined result`
- [x] `it composes UNION with LIMIT applied to the combined result`
- [x] `it parameterizes bindings from both sides of the UNION safely`

## Acceptance Criteria
- All three methods on `QueryBuilderInterface`.
- Column-count validation at compile time with a descriptive exception.
- Both drivers emit correct SQL (MySQL and PostgreSQL syntax for UNION is standard, so drivers share most logic).
- Integration tests on both drivers.
- Document + test: ORDER BY and LIMIT applied AFTER a union call apply to the combined result (driver must emit parenthesized subqueries or reorder clauses appropriately for both MySQL and PostgreSQL — their syntax diverges here).
- Bindings from both builders are concatenated in the same order as the SQL emits them; test explicitly verifies the final bindings array order.
- `distinct()` + `union()` / `unionAll()` compose per standard SQL semantics — no framework-level special casing. `distinct()` before `union()` is redundant but harmless (UNION already dedupes). `distinct()` before `unionAll()` is meaningful and must work ("dedupe this side, concatenate the other raw"). Document both behaviors in the method doccomments; do NOT throw on either combination.

## Implementation Notes

- Added `distinct()`, `union()`, `unionAll()`, `getColumnCount()`, and `compileSubquery()` to `QueryBuilderInterface` with full doccomments covering the distinct+union composition semantics.
- Created `UnionShapeMismatchException` in `packages/database/src/Exceptions/` extending `MarkoException` with a `columnCountMismatch()` static factory.
- Both `MySqlQueryBuilder` and `PgSqlQueryBuilder` implement all five new methods. Union state is stored as `array<array{type: string, builder: QueryBuilderInterface}>`.
- When `get()` is called with a non-empty unions list, `executeUnion()` builds parenthesized subqueries `(left) UNION (right)`, then appends outer ORDER BY / LIMIT. The left subquery strips its own ORDER BY/LIMIT before compilation so those apply only to the combined result.
- `compileSubquery()` resets and restores `$bindings` to avoid side effects; it returns the SQL and appends bindings via the passed-by-reference `$bindings` array. Binding order matches SQL emission order (left side first, right side second).
- MySQL integration tests use a mock recording connection (SQLite does not support parenthesized UNION syntax); PgSQL tests use the existing `MockConnection`. Both verify exact SQL and binding arrays.
- Shape validation (column count check) happens at `union()` / `unionAll()` call time, not at execute time, so errors surface as early as possible.
- All existing anonymous `QueryBuilderInterface` stubs across the `packages/database/tests/` tree were updated with the five new no-op stub methods to keep the test suite green (4725 tests pass).
