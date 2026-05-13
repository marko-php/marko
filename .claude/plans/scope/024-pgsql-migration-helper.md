# Task 024: PostgreSQL auto-migration integration test for `ScopedOverridesEntity`

**Status**: complete
**Depends on**: 010, 022
**Retry count**: 0

## Description
Integration test that registers a `Product` parent entity and a `ProductScopedOverrides` extender, then runs `MigrationGenerator` against an empty PostgreSQL schema. Asserts that the emitted ALTER TABLE includes `ADD COLUMN "scopes" JSONB` — PG's `PgSqlGenerator::TYPE_MAP` aliases `'json' → 'JSONB'` at DDL time, so the same `Column::type = 'json'` from the extender materialises as JSONB on this driver. Sibling of task 020.

Replaces the previously-planned `PgSqlScopeColumn` helper for the same reason: developers should not need to author migration code when the existing pipeline handles it.

## Context
- Related files: `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` (new)
- Patterns to follow: Mirror task 020 structure section-by-section. Use the PG SQL generator instead of MySQL.
- Reference: `packages/database-pgsql/src/Sql/PgSqlGenerator.php` TYPE_MAP maps `'json' → 'JSONB'`.

## Requirements (Test Descriptions)
- [x] `it registers a Product entity and a ProductScopedOverrides extender in the SchemaRegistry`
- [x] `it merges the scopes column into the parent products table at schema-build time`
- [x] `it emits ALTER TABLE products ADD COLUMN scopes JSONB when diffing against an empty schema`
- [x] `it does not emit a separate scopes_overrides table`
- [x] `it preserves the parent entitys existing columns in the merged schema`
- [x] `it aliases json to JSONB via PgSqlGenerator TYPE_MAP rather than emitting JSON`
- [x] `it does not emit any ALTER TABLE statement when the scopes column already exists`

## Acceptance Criteria
- Feature test under `packages/scope-pgsql/tests/Feature/`.
- Uses the actual `MigrationGenerator` + PG SQL generator — no mocking.
- Asserts on emitted SQL string, no live DB connection required.
- Test fixtures file-top per `.claude/testing.md` standards.

## Implementation Notes
- Created `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` with file-top fixtures (`Product` and `ProductScopedOverrides` classes).
- All tests use `SchemaRegistry` + `DiffCalculator` + `PgSqlGenerator` directly — no mocking.
- The "empty schema" for requirement 3 means the products table exists in DB but lacks the scopes column, producing ALTER TABLE ADD COLUMN.
- `PgSqlGenerator::TYPE_MAP` maps `'json' → 'JSONB'` so the scopes column (type=json from `ScopedOverridesEntity`) emits JSONB in DDL.
- Database schema column types must match entity types exactly (e.g., `varchar` not `string`) to avoid spurious diffs.
