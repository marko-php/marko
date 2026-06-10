# Task 002: F1 — Harden MySqlQueryBuilder against identifier + operator injection

**Status**: pending
**Depends on**: [001]
**Retry count**: 0

## Description
Route every non-raw identifier in `MySqlQueryBuilder` through `IdentifierValidator::assertValidIdentifier()`, validate every stored comparison operator against the allowlist via `assertValidOperator()`, and make `quoteIdentifier()` escape embedded backticks (` ` ` → ` `` `) per part. Covers the methods that currently skip validation: `where`, `orWhere`, `whereIn`, `whereNull`, `whereNotNull`, `join`/`leftJoin`/`rightJoin` (table + both columns + operator), `orderBy`, `table`, `count`, and `insert`/`update` column keys.

## Context
- Related files:
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php` (quoteIdentifier ~569; where ~192; orWhere ~269; whereIn ~207; whereNull ~219; whereNotNull ~227; join ~328; leftJoin ~345; rightJoin ~362; orderBy ~379; table ~108; count ~511; insert ~453; update ~475; buildWhereClause ~805)
  - `packages/database-mysql/tests/` (Pest Unit/Feature)
- Patterns to follow:
  - Existing aggregate guards in the same file (`min`/`max`/`sum`/`avg`) that call `IdentifierValidator::isValidIdentifier()` then throw `InvalidColumnException::invalidColumn()`.
  - Use the new `IdentifierValidator::assertValidIdentifier()` / `assertValidOperator()` / `escapeDelimiter()` from task 001.
  - Do NOT change `selectRaw`/`whereRaw`/`orderByRaw` (already validated via `assertNoDangerousPatterns`).
- CRITICAL gotchas (verified against source):
  - `where()`/`orWhere()` columns and `orderBy()` columns may be JSON paths (`data->name`, `data->>user`). `buildWhereClause()` already branches on `JsonPathParser::isJsonPath($where['column'])` → `compileJsonExtract()` vs `quoteIdentifier()`. Apply `assertValidIdentifier()` ONLY in the non-JSON-path branch (the `else` that currently calls `quoteIdentifier()`). A naive validation on every column will reject `->`/`->>` and break the existing `MySqlJsonQueryBuilderTest` cases (`where('data->name', '=', 'Bob')` etc.). Same carve-out for `orderBy()`/SELECT columns.
  - Operators are stored verbatim on `where`/`orWhere`/`join` and emitted in `buildWhereClause()`/`buildJoinClause()` via `sprintf('%s %s ?', ...)`. Validate the operator at the storage method (`where`/`orWhere`/`join`/`leftJoin`/`rightJoin`) so the loud error surfaces at the call site, not deep in SQL build.
  - `count(?string $column)` accepts `null` (→ `COUNT(*)`); only validate when `$column !== null`. Do not reject the `null`/`*` case.
  - `quoteIdentifier()` splits on `.`; escape the backtick delimiter PER PART (after splitting), then wrap — `escapeDelimiter($part, '`')`. Escaping the whole string before splitting would corrupt qualified identifiers.
  - `where()`/`orWhere()` validation must reject the column, NOT just rely on `quoteIdentifier()` escaping — escaping a backtick prevents breakout but a column like `a` + comment still needs the identifier assertion. Validate at the storage method.

## Requirements (Test Descriptions)
- [ ] `it escapes an embedded backtick in a column name when quoting an identifier`
- [ ] `it rejects a where column containing a backtick or SQL comment`
- [ ] `it rejects a where operator not in the allowlist`
- [ ] `it rejects an orWhere operator not in the allowlist`
- [ ] `it rejects a whereIn column that is not a valid identifier`
- [ ] `it rejects a whereNull column that is not a valid identifier`
- [ ] `it rejects an orderBy column that is not a valid identifier`
- [ ] `it rejects a join operator not in the allowlist`
- [ ] `it rejects a join table or column that is not a valid identifier`
- [ ] `it rejects a table name that is not a valid identifier`
- [ ] `it rejects an insert column key that is not a valid identifier`
- [ ] `it rejects an update column key that is not a valid identifier`
- [ ] `it still compiles a JSON-path where column (data->name) without rejecting it as an invalid identifier`
- [ ] `it still allows count() with no column (COUNT(*))`
- [ ] `it still builds a valid SELECT with a qualified identifier and an allowlisted operator`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
