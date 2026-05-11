# Task 008: SchemaBuilder — include extension columns in table schema

**Status**: pending
**Depends on**: [005]
**Retry count**: 0

## Description
Update `SchemaBuilder::build()` to append columns from all registered extensions onto the table schema it produces. Since extension columns live in the same table, they must be included in the `Table` value object that the diff/migration system uses.

## Context
- Related files:
  - `packages/database/src/Entity/SchemaBuilder.php`
  - `packages/database/src/Entity/EntityMetadata.php` (with extensions from task 005)
  - `packages/database/src/Entity/ExtensionMetadata.php`
  - `packages/database/tests/Entity/SchemaBuilderTest.php` — extend with extension scenarios
- `SchemaBuilder::build()` currently maps `$metadata->columns` to `Column` schema objects. Build a merged `ColumnMetadata[]` array consisting of `$metadata->columns` followed by every extension's `$extensionMetadata->columns`, then map the merged set through `buildColumn()` in a single pass.
- Foreign key building (`buildForeignKeys()`) must receive the SAME merged `ColumnMetadata[]` set, so that extension columns with `references` produce FK schema entries. The FK name format `fk_{tableName}_{columnName}` already disambiguates extension FKs by column name.
- Extension columns do not contribute indexes (out of scope); do not add index logic.
- **Column ordering**: base entity columns appear FIRST (preserving primary key position), then extension columns appended in registry-iteration order. Document that the ordering between extensions is non-deterministic across systems and SHOULD NOT be relied on for migration diffs that compare ordinal positions — only column names matter for the diff system (see `packages/database/src/Diff/`).
- **Column conflict assumption**: this task assumes column name conflicts have already been rejected in task 005's metadata factory pass. `SchemaBuilder` does NOT re-validate.

## Requirements (Test Descriptions)
- [ ] `it includes extension columns in the built Table schema`
- [ ] `it builds correct column types for extension columns`
- [ ] `it includes foreign key constraints from extension columns that reference other tables`
- [ ] `it produces the same Table as before when no extensions are registered`
- [ ] `it includes columns from multiple extensions`

## Acceptance Criteria
- All requirements have passing tests
- Existing `SchemaBuilderTest` tests continue to pass
- Code follows project standards
