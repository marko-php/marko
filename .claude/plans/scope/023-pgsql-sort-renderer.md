# Task 023: `PgSqlScopeSortRenderer`

**Status**: complete
**Depends on**: 015, 022
**Retry count**: 0

## Description
Implements `ScopeSortRendererInterface` for PostgreSQL. Emits a COALESCE expression over jsonb `->>` path lookups and the fallback column, in declared-axis-priority order, matching the PHP walker's resolution and the MySQL renderer's semantics.

## Context
- Related files: `packages/scope-pgsql/src/Query/PgSqlScopeSortRenderer.php` (new)
- Patterns to follow: `.claude/sibling-modules.md` — identical method signatures with `MySqlScopeSortRenderer` (task 019).
- Reference: PG `scopes->'axis:path'->>'prop'` returns text.

## Requirements (Test Descriptions)
- [x] `it renders a single-axis sort as COALESCE over jsonb path lookups and the fallback column`
- [x] `it renders a multi-axis sort with axes in declared priority order`
- [x] `it composes the JSON key from already-validated axis name and path segments`
- [x] `it validates fallback column, property, and json column identifiers against the safe pattern`
- [x] `it preserves direction asc or desc in the output`
- [x] `it falls back to plain ORDER BY column when the expression has no axis paths`
- [x] `it embeds path segments containing dots correctly into PG jsonb paths (e.g. eu.de)`

## Acceptance Criteria
- Class name `PgSqlScopeSortRenderer` per sibling convention.
- Method signature `render(ScopeSortExpression $expression): string` identical to MySQL sibling.
- Identifier validation strategy mirrors `MySqlScopeSortRenderer` (task 019):
  - Validate `column`, `property`, `jsonColumn` via `IdentifierValidator::isValidIdentifier`.
  - For each path entry, validate `axis` and each dot-segment separately. Never pass the colon-composed key through the identifier validator.
  - Throw `InvalidColumnException` on failure.
- Composes the JSON key as `scopes->'axis:path'->>'prop'` only after the constituent parts pass validation.

## Implementation Notes
- `PgSqlScopeSortRenderer` implements `ScopeSortRendererInterface` in `packages/scope-pgsql/src/Query/`.
- Identifier validation uses `IdentifierValidator::isValidIdentifier` for `column`, `property`, `jsonColumn`, `axis`, and each dot-segment of each path separately.
- PG JSON syntax: `"jsonColumn"->'axis:path'->>'property'` with double-quoted column identifiers and single-quoted JSON keys.
- No-paths fallback: `"column" ASC/DESC`.
- Tests in `packages/scope-pgsql/tests/Unit/Query/PgSqlScopeSortRendererTest.php`.
