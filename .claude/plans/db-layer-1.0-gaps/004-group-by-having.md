# Task 004: GROUP BY / HAVING on QueryBuilder

**Status**: complete
**Depends on**: 006
**Retry count**: 0

## Description
Add `groupBy(string ...$columns)` and `having(string $expression, array $bindings = [])` methods to `QueryBuilderInterface`. Required to make aggregation queries useful in production (reporting, dashboards). `having()` accepts a raw expression string with `?` placeholders plus bindings — same shape as `whereRaw`-style methods, keeping the interface consistent.

Because `having()` accepts a raw expression, document explicitly that the expression string itself is NOT parameterized — callers must not interpolate user input into it; only `?` placeholders with `$bindings` are safe. Consider validating that the expression contains no semicolons or SQL comments (`--`, `/*`) as a guardrail.

## Context
- Related files:
  - `packages/database/src/Query/QueryBuilderInterface.php` — add methods
  - MySQL and PostgreSQL driver implementations
  - Existing `where()` and `orderBy()` methods for signature symmetry
- Patterns to follow: existing fluent builder methods, parameter binding conventions.

## Requirements (Test Descriptions)
- [x] `it adds a single GROUP BY column to the compiled SQL`
- [x] `it adds multiple GROUP BY columns via variadic arguments`
- [x] `it applies HAVING with a parameterized expression`
- [x] `it binds HAVING parameters safely against SQL injection`
- [x] `it composes GROUP BY with WHERE in the correct SQL order (WHERE ... GROUP BY ... HAVING)`
- [x] `it composes GROUP BY with ORDER BY and LIMIT correctly`
- [x] `it validates GROUP BY column identifiers against the alias/identifier whitelist (reuses the whitelist introduced in task 006)`
- [x] `it rejects HAVING expressions containing semicolons or SQL comments`
- [x] `it composes HAVING bindings with WHERE bindings in the correct positional order at execute time`

## Acceptance Criteria
- Both methods exist on `QueryBuilderInterface`.
- Drivers emit SQL in the correct clause order.
- Identifier validation for GROUP BY columns (no injection via column names).
- Integration tests on both MySQL and PostgreSQL.

## Implementation Notes

- Added `groupBy(string ...$columns): static` and `having(string $expression, array $bindings = []): static` to `QueryBuilderInterface`.
- Both methods implemented identically in `MySqlQueryBuilder` and `PgSqlQueryBuilder`.
- `groupBy()` validates each column via `IdentifierValidator::isValidIdentifier()` + a qualified-identifier regex; throws `InvalidColumnException` on invalid input.
- `having()` rejects expressions containing `;`, `--`, `/*`, `*/` via `InvalidColumnException`; bindings are positional `?` placeholders merged into the binding array after WHERE bindings.
- SQL clause order: WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT (inserted `buildGroupByClause()` and `buildHavingClause()` calls between `buildWhereClause()` and `buildOrderByClause()` in `buildSelectSql()`).
- MySQL uses backtick quoting; PostgreSQL uses double-quote quoting — both consistent with existing driver quoting.
- Test files: `packages/database-mysql/tests/Query/MySqlQueryBuilderGroupByTest.php` and `packages/database-pgsql/tests/Query/PgSqlQueryBuilderGroupByTest.php` (9 tests each).
- `MockConnection` for PgSql tests extracted to `packages/database-pgsql/tests/Query/MockConnection.php` (PSR-4 named file) and removed inline from `PgSqlQueryBuilderTest.php`.
- Fixed pre-existing anonymous-class stub incompleteness in `RelationshipLoaderBelongsToManyTest.php` and `SpecEagerLoadCompositionTest.php` (missing interface methods from other in-progress tasks on the branch caused fatal errors in the parallel test runner).
- Also added `whereJsonContains`, `whereJsonExists`, `whereJsonMissing` public methods to `MySqlQueryBuilder` (state properties and `buildWhereClause` handling were already in place from another task; only the public interface methods were missing).
