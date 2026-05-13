# Task 029: Add `orderByRaw` to `QueryBuilderInterface` and `RepositoryQueryBuilder`

**Status**: complete
**Depends on**: none

## Description
`ScopedOrderBy` (task 016) needs to emit a `COALESCE(...)` expression in the `ORDER BY` clause. The current `QueryBuilderInterface::orderBy(string $column, string $direction)` quotes its first argument as an identifier (see `MySqlQueryBuilder::buildOrderByClause` line 858 — `quoteIdentifier($order['column'])`). There is no raw-expression escape hatch.

This task adds a new method `orderByRaw(string $expression, string $direction = 'ASC'): static` to:
- `Marko\Database\Query\QueryBuilderInterface`
- `Marko\Database\Repository\RepositoryQueryBuilder` (passes through to the wrapped builder)

The driver implementations land in tasks 030 (MySQL) and 031 (PostgreSQL).

Security: `$expression` must be rejected if it contains `;`, `--`, `/*`, `*/`, or backticks (reuse the validation pattern from `IdentifierValidator::rejectDangerousPatterns`). Direction must be validated against `['ASC', 'DESC']` allowlist.

## Context
- Related files: `packages/database/src/Query/QueryBuilderInterface.php`, `packages/database/src/Repository/RepositoryQueryBuilder.php`
- Patterns to follow: Existing `having($expression, $bindings)` pattern which already accepts raw expressions with dangerous-pattern rejection.

## Requirements (Test Descriptions)
- [x] `it adds orderByRaw to QueryBuilderInterface with expression and direction parameters`
- [x] `it adds orderByRaw to RepositoryQueryBuilder delegating to the wrapped builder`
- [x] `it returns static for chaining`
- [x] `it preserves expression text passing through to driver implementations` (unit test against a fake/mock builder)

## Acceptance Criteria
- `orderByRaw(string $expression, string $direction = 'ASC'): static` on `QueryBuilderInterface`.
- `RepositoryQueryBuilder::orderByRaw()` delegates to `$this->queryBuilder->orderByRaw(...)` and returns `$this`.
- Documentation in the interface PHPDoc notes the security caveat (no semicolons, comments, etc.) and that the caller is responsible for using `?` for any user-supplied values.

## Implementation Notes
- Added `orderByRaw(string $expression, string $direction = 'ASC'): static` to `QueryBuilderInterface` with security PHPDoc.
- Added `orderByRaw()` to `RepositoryQueryBuilder` delegating to the wrapped builder.
- Added `rawOrders` property and updated `buildOrderByClause` in `MySqlQueryBuilder` and `PgSqlQueryBuilder` to support raw expressions (minimal implementation to satisfy the interface; full driver tests in tasks 030/031).
- Security validation (rejecting `;`, `--`, `/*`, `*/`, backticks) implemented in both driver classes.
- All existing anonymous `QueryBuilderInterface` stub implementations in tests updated to include `orderByRaw`.
