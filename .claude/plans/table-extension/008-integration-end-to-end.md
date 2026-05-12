# Task 008: End-to-end integration test (parent + extender)

**Status**: completed
**Depends on**: 005, 006, 007
**Retry count**: 0

## Description
Add an integration test that exercises the full Table Extension flow against a real SQLite (or in-memory) database driver: declare a parent entity, declare one or two extenders, register them via `SchemaRegistry::registerEntities()`, build the schema, write rows, read them back, modify both parent and companion fields, save again, and verify the SQL effects.

Also verify migrate-diff parity: if the framework currently exposes a `SchemaDiffer` or equivalent (used by `migrate:diff`), invoke it against an existing DB whose `users` table is missing the extender's columns; assert it produces ADD COLUMN statements for the extender columns. If no such public differ is available at the framework level, fall back to asserting on the merged `Table` value object's columns — but in that case remove the broader claim from `_plan.md` Scope ("falls out for free... worth verifying"). Pick exactly one of these and document it in this task.

Also exercise the discovery path: place the parent and extender entity fixtures under a directory layout that `EntityDiscovery::discoverInPath()` will find, run discovery, then registration, and confirm the extender is included in the merged schema. This is the proof that the plan's "EntityDiscovery needs no changes" claim holds.

## Context
- Related files:
  - `packages/database/tests/Integration/TableExtensionIntegrationTest.php` (new)
  - `packages/database/tests/Feature/` or wherever integration tests live — check repo conventions
  - Reference existing integration test scaffolding for connection/migration setup
- Patterns to follow:
  - Existing integration tests in `packages/database/tests/` for fixture setup
  - Use the in-memory SQLite or test connection already configured for the suite

## Requirements (Test Descriptions)
- [x] `it creates a table with merged parent and extender columns from schema registry`
- [x] `it inserts a parent entity with companion data in a single INSERT statement`
- [x] `it reads back parent and companion data via Repository find from a single SELECT`
- [x] `it updates parent and companion fields in a single UPDATE when both are dirty`
- [x] `it persists changes correctly when only the companion is dirty`
- [x] `it silently skips companion hydration when the extender's columns are dropped from the schema (rolling deploy)`
- [x] `it detects column-name conflicts at registration time across two extenders`
- [x] `it preserves migrate diff parity by including extender columns in the parent's Table value object`
- [x] `it discovers an extender via EntityDiscovery and registers it correctly into the parent's merged schema`
- [x] `it raises a loud error when constructing a Repository against an extender class`
- [x] `it raises a loud error when insertBatch is called on entities with companions attached`

## Implementation Notes

- **Migrate-diff parity approach chosen**: Assert on the merged `Table` value object's columns rather than invoking a real DB-backed `SchemaDiffer`. The `DiffCalculator` is exercised directly against a hand-crafted "existing DB table" missing the extender columns, and the test asserts that the resulting `TableDiff.columnsToAdd` contains `bio` and `timezone`. This is the correct approach because the merged `Table` value object is the same object handed to `DiffCalculator` in production — so if the columns are in the `Table`, the differ will produce the right ADD COLUMN statements.
- **Fixtures location**: `packages/database/tests/Integration/Fixtures/` — named classes `IntUser` (parent), `IntUserProfile` (extender 1), `IntUserSettings` (extender 2, conflict-only fixture).
- **SQLite connection**: inline `PDO('sqlite::memory:')` anonymous class implementing `ConnectionInterface` — no external driver package required.
- **EntityDiscovery test**: uses `discoverInPath()` against the `Fixtures/` directory; filters out `IntUserSettings` before registration to avoid the conflict.

## Acceptance Criteria
- All requirements have passing tests
- Integration test runs against a real connection (not mocked) — use the same setup pattern as existing integration tests
- Tests cover the rolling-deploy scenario by manually dropping a column and re-running hydrate
- No regressions in the existing `composer test` suite
- Code follows code standards
