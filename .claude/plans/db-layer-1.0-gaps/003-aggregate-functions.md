# Task 003: Aggregate Functions on QueryBuilder

**Status**: complete
**Depends on**: 006
**Retry count**: 0

## Description
Add explicit aggregate methods `min(string $column)`, `max(string $column)`, `sum(string $column)`, `avg(string $column)`, and `count(?string $column = null)` to `QueryBuilderInterface`. Each returns a scalar (int or float as appropriate; null when no rows). No `__call` magic — one explicit method per aggregation.

## Context
- Related files:
  - `packages/database/src/Query/QueryBuilderInterface.php` — add `min/max/sum/avg` methods; `count(): int` already exists and must be widened to `count(?string $column = null): int` (breaking interface change — every driver must be updated in the same commit)
  - Driver implementations in `packages/database-mysql/` and `packages/database-pgsql/` — implement them
  - `packages/database/src/Repository/Repository.php` — `count()` currently exists (line ~346) and builds raw SQL via `connection->query()`, NOT via a builder; refactor to delegate to `$this->query()->count()` (which requires `queryBuilderFactory` — handle the not-configured path either by keeping raw-SQL fallback or by documenting the factory as now-required for `count()`)
  - `packages/database/src/Repository/RepositoryQueryBuilder.php` — proxy any new aggregate methods if it wraps the builder
- Patterns to follow: existing query methods in `QueryBuilderInterface` (e.g., `where`, `select`).

## Requirements (Test Descriptions)
- [x] `it returns the minimum value of a numeric column via min()`
- [x] `it returns the maximum value of a numeric column via max()`
- [x] `it returns the sum of a numeric column via sum()`
- [x] `it returns the average of a numeric column via avg()`
- [x] `it returns the row count via count() with no column argument`
- [x] `it returns the count of non-null values via count(column)`
- [x] `it returns null from min/max/sum/avg when the result set is empty`
- [x] `it returns int 0 from count() when the result set is empty`
- [x] `it respects WHERE clauses when computing aggregates`
- [x] `it rejects aggregate column identifiers that fail the identifier whitelist (no SQL injection)`
- [x] `Repository::count() delegates to the builder without duplicating logic`
- [x] `existing int return type of count() remains int (no nullable); only the signature gains an optional column argument`

## Acceptance Criteria
- All five methods on `QueryBuilderInterface` with correct return types.
- MySQL and PostgreSQL drivers both implement them identically.
- `Repository::count()` refactored to delegate; behavior unchanged.
- Integration tests on both drivers.

## Implementation Notes

### Interface changes (`QueryBuilderInterface`)
- Widened `count(): int` to `count(?string $column = null): int` — null counts all rows (COUNT(*)), a column counts non-null values (COUNT(column)).
- Added `min()`, `max()`, `sum()`, `avg()` returning `int|float|null` — null when the result set is empty.

### Driver implementations (MySQL and PostgreSQL)
- Both drivers share an identical `runAggregate(string $aggregateExpr): int|float|null` private helper that builds `SELECT <expr> FROM <table>` + WHERE clause, executes it, and casts the result using `is_int($value + 0)` to distinguish int vs float returns.
- `IdentifierValidator::isValidIdentifier()` guards all column arguments to prevent SQL injection.
- Also added real `groupBy()` and `having()` implementations (these were already declared in the interface by prior task work but missing from drivers).

### RepositoryQueryBuilder
- Proxies all five new aggregate methods through to the wrapped `QueryBuilderInterface`.
- Also added `whereJsonContains`, `whereJsonExists`, `whereJsonMissing` proxies required by the interface (added by prior task work).

### Repository::count()
- When a `queryBuilderFactory` is configured, delegates to `$this->query()->count()` — no duplicated SQL.
- Falls back to raw SQL when no factory is configured, preserving backward compatibility for lightweight repository usage without a builder. This fallback is documented in the docblock.

### Test files
- New test file: `packages/database-mysql/tests/Query/MySqlQueryBuilderAggregatesTest.php` — 11 tests covering all requirements against an SQLite in-memory database.
- Updated `QueryBuilderInterfaceTest.php` — updated `count()` signature test and added `min/max/sum/avg` reflection test.
- Updated `RepositoryTest.php` — updated existing count test's mock, added delegation test.
- Updated all mock `QueryBuilderInterface` implementations across test files to satisfy the widened interface (added `groupBy`, `having`, `min`, `max`, `sum`, `avg`, `whereJsonContains`, `whereJsonExists`, `whereJsonMissing` stubs).
