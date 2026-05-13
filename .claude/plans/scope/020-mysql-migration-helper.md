# Task 020: MySQL auto-migration integration test for `ScopedOverridesEntity`

**Status**: complete
**Depends on**: 010, 018
**Retry count**: 0

## Description
Integration test that registers a `Product` parent entity and a `ProductScopedOverrides` extender, then runs `MigrationGenerator` against an empty MySQL schema. Asserts that the emitted ALTER TABLE includes `ADD COLUMN scopes JSON NULL`. Locks in the contract that the existing `SchemaRegistry` + `MigrationGenerator` pipeline picks up the override extender automatically — no migration helper required from `marko/scope-mysql`.

This task replaces the previously-planned `MySqlScopeColumn` helper. The helper added no real value beyond a typed `Column` factory while the developer still had to know the parent table and write the migration. The auto-migration path is preferable because it requires zero developer migration code: declaring the extender entity is sufficient.

## Context
- Related files: `packages/scope-mysql/tests/Feature/AutoMigrationTest.php` (new)
- Patterns to follow: Existing feature tests under `packages/database-mysql/tests/Feature/`; `SchemaRegistry::register()` (lines 116–177) merges extender columns into the parent's table; `MigrationGenerator` diffs target schema against current schema and emits ALTER TABLE statements.
- Test fixtures (`Product`, `ProductScopedOverrides`) defined file-top per `.claude/testing.md` § Test Fixtures at File Top.

## Requirements (Test Descriptions)
- [x] `it registers a Product entity and a ProductScopedOverrides extender in the SchemaRegistry`
- [x] `it merges the scopes column into the parent products table at schema-build time`
- [x] `it emits ALTER TABLE products ADD COLUMN scopes JSON NULL when diffing against an empty schema`
- [x] `it does not emit a separate scopes_overrides table or treat the extender as a standalone table`
- [x] `it preserves the parent entitys existing columns in the merged schema`
- [x] `it does not emit any ALTER TABLE statement when the scopes column already exists`

## Acceptance Criteria
- Feature test under `packages/scope-mysql/tests/Feature/`.
- Uses the actual `MigrationGenerator` + MySQL SQL generator — no mocking of generator output.
- Asserts on emitted SQL string, so the test runs without a live DB connection.
- Test fixtures use the file-top fixture pattern from `.claude/testing.md`.

## Implementation Notes
- Created `packages/scope-mysql/tests/Feature/AutoMigrationTest.php` with file-top fixture classes `AutoMigrationProduct` and `AutoMigrationProductScopedOverrides`.
- `AutoMigrationProductScopedOverrides` extends `ScopedOverridesEntity` with `#[Table(extends: AutoMigrationProduct::class)]` — no extra code needed in the extender body.
- Tests use actual `SchemaRegistry`, `DiffCalculator`, and `MySqlGenerator` — no mocking.
- For the ALTER TABLE test, the "existing" DB schema must use the abstract types (`integer`, `varchar`) that `EntityMetadataFactory` produces (not MySQL native types like `INT`, `VARCHAR`) to avoid spurious MODIFY COLUMN statements in the diff.
- All 6 requirements passed; tests run without a live DB connection.
