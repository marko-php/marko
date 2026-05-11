# Task 007: Repository — include extension columns in INSERT/UPDATE

**Status**: pending
**Depends on**: [005, 006]
**Retry count**: 0

## Description
Update `Repository::insert()` and `Repository::update()` to include extension column data when persisting entities. For INSERT: extract all extension data and merge with base data. For UPDATE: always write all extension columns (no dirty-checking on extensions). When an extension is not attached to the entity being saved, apply the null/default/error policy per column.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php`
  - `packages/database/src/Entity/EntityMetadata.php` (with extensions)
  - `packages/database/src/Entity/ExtensionMetadata.php`
  - `packages/database/src/Entity/PropertyMetadata.php`
  - `packages/database/src/Entity/EntityHydrator.php` — may need a new public helper (see below)
  - `packages/database/src/Exceptions/RepositoryException.php` — add new factory method
  - `packages/database/tests/Repository/RepositoryTest.php` — extend with extension tests
- **Null/default/error policy** when extension is not attached:
  - Column nullable → use `null`
  - Column non-nullable AND `PropertyMetadata::$default` is not `null` → use that default
  - Column non-nullable AND no default → throw `RepositoryException` with a clear message naming the extension class and the column (new factory method: `RepositoryException::extensionRequired(string $entityClass, string $extensionClass, string $columnName)`)
- **Extraction**: add a new public helper `EntityHydrator::extractExtensions(Entity $entity, EntityMetadata $metadata): array<string, mixed>` that returns column-name => DB-value for every extension column on the entity, applying the null/default/error policy above. This keeps reflection-based conversion logic centralised in the hydrator. `Repository::insert()` and `Repository::update()` then merge the result with the base `extract()` output.
- **UPDATE early-return bug**: `Repository::update()` currently returns early when `count($dirtyProperties) === 0`. Since UPDATE must always write all extension columns, this short-circuit must change: only skip if BOTH `$dirtyProperties` is empty AND `$metadata->extensions` is empty. When base is clean but extensions exist, still issue the UPDATE statement for extension columns only (plus WHERE on PK).
- For UPDATE, always write ALL extension columns (not just dirty ones). The SET clause includes all extension column names, even if unchanged. This is a conscious simplification.
- **`insertBatch()` consistency**: `extractBatchRow()` is called per-entity and the column sets are compared via `array_keys`. Because all rows in a batch share the same entity class and the extension column set is determined by `$metadata->extensions` (not by which extensions are attached), `extractBatchRow()` must always include the SAME extension columns for every row in the batch — applying the null/default policy per-row independently. If any row in the batch lacks an extension that another row has AND the policy throws (non-nullable, no default), the batch fails for that entity (loud error). Document this explicitly.
- Auto-increment PK exclusion in `extractBatchRow()` only strips the base entity PK column — extension columns are never primary keys (enforced by task 002), so they are always included.
- The base-entity `extract()` already converts BackedEnum and DateTimeImmutable values; the new extension extractor must do the same (reuse `convertToDbValue` logic — currently `private` in hydrator; if needed, factor it into a shared private method called by both `extract()` and `extractExtensions()`).

## Requirements (Test Descriptions)
- [ ] `it includes extension columns in the INSERT statement when extension is attached`
- [ ] `it uses null for nullable extension columns when extension is not attached`
- [ ] `it uses declared default for non-nullable extension columns when extension is not attached`
- [ ] `it throws when a non-nullable extension column has no default and no extension is attached`
- [ ] `it includes extension columns in the UPDATE statement`
- [ ] `it still issues an UPDATE for extension columns when no base entity properties are dirty`
- [ ] `it does not issue an UPDATE when there are no dirty base properties and no extensions registered`
- [ ] `it includes extension columns in batch insert`
- [ ] `it writes correct extension values for multiple extensions on the same entity`
- [ ] `it converts BackedEnum extension property values to backing values before persisting`
- [ ] `it converts DateTimeImmutable extension property values to formatted strings before persisting`

## Acceptance Criteria
- All requirements have passing tests
- Existing repository tests continue to pass without modification
- New factory method added to `Marko\Database\Exceptions\RepositoryException`: `extensionRequired(string $entityClass, string $extensionClass, string $columnName)`
- New public method on `EntityHydrator`: `extractExtensions(Entity $entity, EntityMetadata $metadata): array<string, mixed>`
- Code follows project standards
