# Task 003: F1 — Harden PgSqlQueryBuilder against identifier + operator injection (parity with mysql)

**Status**: complete
**Depends on**: [001]
**Retry count**: 0

## Description
Apply the exact same hardening as task 002 to `PgSqlQueryBuilder` so the two drivers reach behavioral parity. Route every non-raw identifier through `IdentifierValidator::assertValidIdentifier()`, validate stored operators via `assertValidOperator()`, and make `quoteIdentifier()` escape embedded double-quotes (`"` → `""`) per part. Same method coverage: `where`, `orWhere`, `whereIn`, `whereNull`, `whereNotNull`, `join`/`leftJoin`/`rightJoin`, `orderBy`, `table`, `count`, `insert`/`update` keys.

## Context
- Related files:
  - `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (quoteIdentifier ~573; where ~191; orWhere ~268; whereIn ~206; whereNull ~218; whereNotNull ~226; join ~327; leftJoin ~344; rightJoin ~361; orderBy ~378; table ~108; count ~521; insert ~451; update ~482; buildWhereClause ~803)
  - `packages/database-pgsql/tests/` (Pest Unit/Feature)
- Patterns to follow:
  - Mirror task 002 exactly; only the quote delimiter differs (double-quote, escaped by doubling).
  - Existing aggregate guards (`min`/`max`/`sum`/`avg`) in the same file.
  - Do NOT change `selectRaw`/`whereRaw`/`orderByRaw`.
- CRITICAL gotchas (same as task 002, verified against `PgSqlQueryBuilder` source):
  - `where()`/`orWhere()`/`orderBy()`/SELECT columns may be JSON paths; `buildWhereClause()` branches on `JsonPathParser::isJsonPath()` → `compileJsonExtract()` vs `quoteIdentifier()`. Apply `assertValidIdentifier()` ONLY on the non-JSON-path branch. A naive per-column validation will break the existing pgsql JSON query-builder tests.
  - Validate operators at the storage methods (`where`/`orWhere`/`join`/`leftJoin`/`rightJoin`).
  - `count(?string $column)` accepts `null` (→ `COUNT(*)`); only validate when non-null.
  - `quoteIdentifier()` splits on `.`; escape the double-quote delimiter PER PART (after splitting) via `escapeDelimiter($part, '"')`, then wrap.

## Requirements (Test Descriptions)
- [x] `it escapes an embedded double-quote in a column name when quoting an identifier`
- [x] `it rejects a where column containing a double-quote or SQL comment`
- [x] `it rejects a where operator not in the allowlist`
- [x] `it rejects an orWhere operator not in the allowlist`
- [x] `it rejects a whereIn column that is not a valid identifier`
- [x] `it rejects a whereNull column that is not a valid identifier`
- [x] `it rejects an orderBy column that is not a valid identifier`
- [x] `it rejects a join operator not in the allowlist`
- [x] `it rejects a join table or column that is not a valid identifier`
- [x] `it rejects a table name that is not a valid identifier`
- [x] `it rejects an insert column key that is not a valid identifier`
- [x] `it rejects an update column key that is not a valid identifier`
- [x] `it still compiles a JSON-path where column (data->name) without rejecting it as an invalid identifier`
- [x] `it still allows count() with no column (COUNT(*))`
- [x] `it still builds a valid SELECT with a qualified identifier and an allowlisted operator`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- `quoteIdentifier()` updated to escape embedded `"` per part using `IdentifierValidator::escapeDelimiter($part, '"')` before wrapping in double-quotes.
- `table()` validates identifier via `IdentifierValidator::assertValidIdentifier()`.
- `where()` and `orWhere()` guard with `JsonPathParser::isJsonPath()` before calling `assertValidIdentifier()` (JSON paths bypass the identifier check), then validate operator via `assertValidOperator()`.
- `whereIn()`, `whereNull()`, `whereNotNull()` validate column via `assertValidIdentifier()`.
- `orderBy()` guards with `JsonPathParser::isJsonPath()` before calling `assertValidIdentifier()`.
- `join()`, `leftJoin()`, `rightJoin()` validate table, first, operator, and second identifiers/operator.
- `insert()` validates each column key before building the query.
- `update()` validates each column key in the foreach loop.
- `count()` validates non-null column via `assertValidIdentifier()`.
- All 15 requirements implemented with tests in `packages/database-pgsql/tests/Query/PgSqlQueryBuilderHardeningTest.php`.
- 194 total tests pass (179 pre-existing + 15 new), no regressions.
