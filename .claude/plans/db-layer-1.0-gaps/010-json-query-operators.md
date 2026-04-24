# Task 010: JSON Query Operators on QueryBuilder

**Status**: complete
**Depends on**: 006, 008
**Retry count**: 0

## Description
Add JSON path query operators to the query builder so JSON columns are first-class queryable — not just a "blob you can store." This is what makes JSON a real alternative to Magento-style EAV and a pragmatic in-SQL alternative to spinning up a separate NoSQL store.

Scope:
1. **Path extraction in WHERE** — `$qb->where('data->user->name', '=', 'Bob')`
2. **Path extraction in SELECT** — `$qb->select('data->user->name as user_name')` (composes with the aliasing work from task 006)
3. **Containment** — `$qb->whereJsonContains('data->tags', 'premium')` (MySQL `JSON_CONTAINS`, PostgreSQL `@>`)
4. **Path existence** — `$qb->whereJsonExists('data->middle_name')` / `whereJsonMissing(...)` (MySQL `JSON_CONTAINS_PATH`, PostgreSQL `jsonb_path_exists`)
5. **Text vs JSON extraction semantics** — `->` returns JSON (preserves type), `->>` returns text (matches PostgreSQL convention; MySQL wraps with `JSON_UNQUOTE`)

Indexing is explicitly NOT a new API in 1.0. Users create JSON indexes via migrations using raw DDL. Documented patterns (PostgreSQL GIN + `jsonb_path_ops`, MySQL generated columns + index) are handled by the doc-updater pass.

## Context
- Related files:
  - `packages/database/src/Query/QueryBuilderInterface.php` — add `whereJsonContains`, `whereJsonExists`, `whereJsonMissing` methods; extend path parsing in `where()` and `select()` to recognize arrow syntax
  - `packages/database/src/Query/IdentifierValidator.php` (from task 006) — extend to validate JSON path segments (each segment between `->` / `->>` must pass identifier whitelist)
  - MySQL and PostgreSQL driver implementations — each translates the abstract path expression to its native SQL dialect
  - `packages/database/src/Exceptions/` — `InvalidJsonPathException` or reuse `InvalidAliasException`
- Patterns to follow: existing `where()` and `select()` flow; driver-specific SQL compilation patterns already used for identifier quoting.

## Requirements (Test Descriptions)
- [x] `it parses a two-segment JSON path with -> (data->name)`
- [x] `it parses a deeply nested JSON path with multiple -> segments (data->user->address->city)`
- [x] `it distinguishes -> (JSON-typed result) from ->> (text-typed result) in path expressions`
- [x] `it rejects JSON path segments that fail the identifier whitelist (no injection)`
- [x] `it rejects JSON path expressions containing semicolons or SQL comments`
- [x] `it filters rows by a nested JSON path value via where()`
- [x] `it selects a nested JSON path as an aliased column`
- [x] `it returns rows whose JSON array contains a value via whereJsonContains()`
- [x] `it returns rows whose JSON object contains a nested value via whereJsonContains() with a path`
- [x] `it returns rows where a JSON path exists via whereJsonExists()`
- [x] `it returns rows where a JSON path does NOT exist via whereJsonMissing()`
- [x] `it parameterizes JSON query values safely (no injection via the value side)`
- [x] `it emits correct MySQL SQL for every JSON operator (JSON_EXTRACT / JSON_UNQUOTE / JSON_CONTAINS / JSON_CONTAINS_PATH)`
- [x] `it emits correct PostgreSQL SQL for every JSON operator (-> / ->> / @> / jsonb_path_exists)`
- [x] `it composes JSON path operators with WHERE, GROUP BY, HAVING, ORDER BY, and LIMIT correctly`

## Acceptance Criteria
- `whereJsonContains`, `whereJsonExists`, `whereJsonMissing` on `QueryBuilderInterface` with explicit signatures (no `__call`).
- Arrow-path syntax in `where()` and `select()` arguments is parsed and driver-translated; plain column names still work identically.
- JSON path segments validated through the shared `IdentifierValidator` from task 006.
- Both MySQL and PostgreSQL drivers fully implement every operator.
- Integration tests on both drivers covering real nested documents.
- No new DDL / indexing API introduced — indexing remains a migration-level concern with documented patterns.

## Implementation Notes

### New files
- `packages/database/src/Query/JsonPathExpression.php` — readonly value object with `column`, `segments[]`, `operator` (`->` or `->>`)
- `packages/database/src/Query/JsonPathParser.php` — parses `data->user->name` and `data->>name`, validates each segment via `IdentifierValidator::isValidIdentifier()`, rejects injection patterns (`;`, `--`, `/*`)
- `packages/database/src/Exceptions/InvalidJsonPathException.php` — extends `MarkoException`, two named constructors: `invalidSegment()` and `invalidPath()`
- `packages/database/tests/Query/JsonPathParserTest.php` — unit tests for parsing, operator distinction, and validation
- `packages/database-mysql/tests/Query/MySqlJsonQueryBuilderTest.php` — MySQL SQL emission tests using `MySqlMockConnection`
- `packages/database-pgsql/tests/Query/PgSqlJsonQueryBuilderTest.php` — PgSQL SQL emission tests using existing `MockConnection`

### Modified files
- `packages/database/src/Query/QueryBuilderInterface.php` — added `whereJsonContains(string $path, mixed $value)`, `whereJsonExists(string $path)`, `whereJsonMissing(string $path)` with `@throws InvalidJsonPathException`
- `packages/database-mysql/src/Query/MySqlQueryBuilder.php` — added JSON state arrays, implemented three new methods, updated `compileColumnExpression()` to detect `->` in SELECT and emit `JSON_EXTRACT`/`JSON_UNQUOTE`, updated `buildWhereClause()` to translate JSON paths in `where()` and process `whereJsonContains`/`whereJsonPaths` arrays
- `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` — same structure; PgSQL uses native `->` / `->>` chaining, `@>` for containment, `jsonb_path_exists` for existence

### SQL dialect mapping
| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| `->` path extraction | `JSON_EXTRACT(col, '$.path')` | `"col"->'key'->'key'` |
| `->>` text extraction | `JSON_UNQUOTE(JSON_EXTRACT(...))` | `"col"->>'key'` |
| `whereJsonContains` | `JSON_CONTAINS(col, ?)` / `JSON_CONTAINS(JSON_EXTRACT(...), ?)` | `col @> ?` |
| `whereJsonExists` | `JSON_CONTAINS_PATH(col, 'one', '$.path')` | `jsonb_path_exists(col, '$.path')` |
| `whereJsonMissing` | `NOT JSON_CONTAINS_PATH(...)` | `NOT jsonb_path_exists(...)` |
