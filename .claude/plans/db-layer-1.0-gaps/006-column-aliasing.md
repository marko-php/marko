# Task 006: Column Aliasing in Select

**Status**: complete
**Depends on**: none
**Retry count**: 1

## Description
Allow `select('users.name as author_name')` and aggregate expressions like `select('COUNT(*) as total')`. Required for disambiguating joined columns and for addressing aggregate results in GROUP BY queries. Parse the select argument into column + optional alias; validate both against a strict identifier whitelist to prevent SQL injection.

This task also introduces the shared identifier-whitelist utility that tasks 003 (aggregate column names) and 004 (GROUP BY columns) will reuse. Extract it into a small validator class (e.g., `IdentifierValidator`) under `packages/database/src/Query/` so every builder feature validates identifiers through the same code path.

## Context
- Related files:
  - `packages/database/src/Query/QueryBuilderInterface.php` — `select()` signature stays, but argument semantics expand
  - MySQL and PostgreSQL driver implementations — compilation must emit quoted identifiers properly
  - `packages/database/src/Exceptions/` — `InvalidAliasException` or reuse existing exception pattern
  - `packages/database/src/Query/IdentifierValidator.php` — NEW file, reusable utility
- Patterns to follow: existing identifier-quoting logic in drivers.

## Requirements (Test Descriptions)
- [x] `it parses a simple column name without alias`
- [x] `it parses a qualified column with table prefix (users.name)`
- [x] `it parses a column with an alias using 'as' keyword (case-insensitive)`
- [x] `it parses an aggregate expression with an alias (COUNT(*) as total)`
- [x] `it rejects an alias containing characters outside [a-zA-Z0-9_]`
- [x] `it rejects an alias that starts with a digit`
- [x] `it rejects a column name containing a SQL comment or semicolon`
- [x] `it quotes both the column and the alias using driver-specific identifier quoting`
- [x] `it returns rows keyed by alias when a select uses an alias`

## Acceptance Criteria
- Alias regex: `/^[a-zA-Z_][a-zA-Z0-9_]*$/` for alias identifiers.
- Qualified columns allow one dot between two identifier segments only.
- Aggregate expressions (`COUNT(*)`, `SUM(column)`, etc.) pass through for known aggregate function names; anything else rejected loudly.
- Both drivers apply correct identifier quoting (`` ` `` for MySQL, `"` for PostgreSQL).
- Integration tests cover round-tripping aliased results.
- A reusable `IdentifierValidator` (or equivalent) lives under `packages/database/src/Query/` with its own unit tests; tasks 003 and 004 will consume it.
- `composer test` passes cleanly.

## Implementation Notes

### New files created
- `packages/database/src/Query/IdentifierValidator.php` — Static utility with `parseSelectExpression()` public method. Validates and parses SELECT column expressions (plain identifiers, qualified `table.column`, known aggregates `COUNT|SUM|MIN|MAX|AVG`), rejects SQL injection patterns (semicolons, `--`, `/*`). Returns `['column' => string, 'alias' => ?string]`. Exposes `AGGREGATE_FUNCTIONS` const array for reuse by tasks 003/004.
- `packages/database/src/Exceptions/InvalidColumnException.php` — Extends `MarkoException` with named factory methods `invalidAlias()` and `invalidColumn()`.
- `packages/database/tests/Query/IdentifierValidatorTest.php` — 7 unit tests covering all parsing and rejection requirements.

### Files modified
- `packages/database-mysql/src/Query/MySqlQueryBuilder.php` — Added `compileColumnExpression()` private method that uses `IdentifierValidator::parseSelectExpression()` and properly quotes column + alias using backticks. Replaced old loose pass-through logic in `buildSelectSql()`.
- `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` — Same pattern with double-quote quoting.
- `packages/database-mysql/tests/Query/MySqlQueryBuilderTest.php` — Added 2 integration tests (requirement 8 and 9).
- `packages/database-pgsql/tests/Query/PgSqlQueryBuilderTest.php` — Added 1 SQL-assertion test (requirement 8).

### Design decisions
- `IdentifierValidator` is a plain class with static methods (no constructor, no state) — fits the pure utility pattern.
- The `count()` method in both drivers still uses the literal `COUNT(*) as count` string directly without going through `IdentifierValidator`, which is safe since it's an internal hardcoded string.
- Aggregate functions pass through unquoted but are validated via allowlist regex; their inner arguments are not re-quoted to preserve `COUNT(*)` intact.
