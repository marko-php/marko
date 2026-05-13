# Task 019: `MySqlScopeSortRenderer`

**Status**: complete
**Depends on**: 015, 018
**Retry count**: 0

## Description
Implements `ScopeSortRendererInterface` for MySQL/MariaDB. Emits a COALESCE expression over JSON_UNQUOTE/JSON_EXTRACT calls and the fallback column, in declared-axis-priority order, matching the PHP walker's resolution.

## Context
- Related files: `packages/scope-mysql/src/Query/MySqlScopeSortRenderer.php` (new)
- Patterns to follow: `.claude/sibling-modules.md` — identical method signatures across siblings (mirror task 023).
- Reference: MySQL `JSON_UNQUOTE(JSON_EXTRACT(col, '$."key"'))` or `col->>"$.key"` shorthand.

## Requirements (Test Descriptions)
- [x] `it renders a single-axis sort as COALESCE over JSON paths and the fallback column`
- [x] `it renders a multi-axis sort with axes in declared priority order`
- [x] `it composes the JSON key from already-validated axis name and path segments`
- [x] `it validates fallback column, property, and json column identifiers against the safe pattern`
- [x] `it preserves direction asc or desc in the output`
- [x] `it falls back to plain ORDER BY column when the expression has no axis paths`
- [x] `it embeds path segments containing dots correctly into MySQL JSON paths (e.g. eu.de)`

## Acceptance Criteria
- Class name `MySqlScopeSortRenderer` per sibling convention.
- Identifier validation strategy:
  - Validate `$expression->column`, `$expression->property`, `$expression->jsonColumn` via `IdentifierValidator::isValidIdentifier`.
  - For each `$path` entry, validate `axis` and each dot-segment of `path` separately. **Never validate the composed `"axis:path"` key — colons would fail the identifier pattern.**
  - On any validation failure, throw `InvalidColumnException` (mirroring marko/database conventions).
- After validation, compose the JSON path as `$."axis:path".prop` and inline-escape — caller-supplied strings have already passed identifier validation so direct interpolation is safe.
- Method signature `render(ScopeSortExpression $expression): string` exactly matches the interface.

## Implementation Notes
- `MySqlScopeSortRenderer` in `packages/scope-mysql/src/Query/` implements `ScopeSortRendererInterface`.
- Validates `column`, `property`, `jsonColumn` with `IdentifierValidator::isValidIdentifier`; for each path validates `axis` and each dot-segment of `path` individually.
- Composes JSON key as `axis:path`, uses `JSON_UNQUOTE(JSON_EXTRACT(\`jsonColumn\`, '$."axis:path".property'))`.
- Empty paths → plain `` `column` ASC/DESC `` fallback.
- Dot-separated path segments (e.g. `eu.de`) appear inside the quoted JSON key so remain literal in the SQL output.
