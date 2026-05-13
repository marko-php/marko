# Task 028: `marko/scope-pgsql` README

**Status**: complete
**Depends on**: 022, 023, 024, 025, 031
**Retry count**: 0

## Description
Write the `marko/scope-pgsql` README per `.claude/code-standards.md` § Package README Standards. Mirror task 027 structure for sibling-module symmetry.

## Context
- Related files: `packages/scope-pgsql/README.md` (new)
- Patterns to follow: `packages/database-pgsql/README.md`. Mirror task 027 structure section-by-section.

## Requirements (Test Descriptions)
- [x] `it has Title + One-Liner section stating the driver provides PostgreSQL jsonb support for marko/scope`
- [x] `it has Overview section of 2-4 sentences explaining ORDER BY emission and auto-migration via the existing extender pipeline`
- [x] `it has Installation section with composer command`
- [x] `it has Usage section noting the scopes column is added automatically by MigrationGenerator when a ScopedOverridesEntity extender is registered — no migration helper needed`
- [x] `it has Usage section noting ORDER BY support is automatic via ScopedOrderBy on Repository::matching`
- [x] `it has API Reference listing PgSqlScopeSortRenderer`
- [x] `it notes Column type json materialises as JSONB via PgSqlGenerator TYPE_MAP`

## Acceptance Criteria
- README exists at `packages/scope-pgsql/README.md`.
- Section structure identical to `marko/scope-mysql`'s README.
- Notes `marko/scope` is pulled in transitively.
- No "migration helper" usage block — explains the auto-migration flow with the one-line `ProductScopedOverrides` extender as the trigger.

## Implementation Notes
- Created `packages/scope-pgsql/tests/Unit/ReadmeTest.php` mirroring the scope-mysql ReadmeTest structure.
- Created `packages/scope-pgsql/README.md` with all required sections.
- README notes `marko/scope` is pulled in transitively and uses `ProductScopedOverrides` as the trigger example.
- No migration helper usage block; auto-migration flow described via `ScopedOverridesEntity` extender.
- `json` → `JSONB` mapping via `PgSqlGenerator::TYPE_MAP` is documented in both Overview and Usage sections.
