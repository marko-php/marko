# Task 006: EntityHydrator — hydrate extensions from same DB row

**Status**: completed
**Depends on**: [004, 005]
**Retry count**: 0

## Description
Update `EntityHydrator::hydrate()` to also hydrate all extension objects registered for the entity from the same DB row and attach them to the entity via `setExtension()`. Because extension columns are in the same table, the row already contains them — no extra query is needed.

## Context
- Related files:
  - `packages/database/src/Entity/EntityHydrator.php`
  - `packages/database/src/Entity/Entity.php` (modified in task 004)
  - `packages/database/src/Entity/EntityMetadata.php` (modified in task 005)
  - `packages/database/src/Entity/ExtensionMetadata.php` (from task 002)
  - `packages/database/tests/Entity/EntityHydratorTest.php` — extend with extension scenarios
- `EntityHydrator::convertToPhpType()` is currently `private` and takes a `PropertyMetadata`. Since `ExtensionMetadata::$properties` is also `array<string, PropertyMetadata>`, the existing private method works as-is for extension property conversion when called from inside `EntityHydrator`. Do NOT change its visibility; reuse it directly from the new extension-hydration code path inside the same class.
- After hydrating base entity properties (existing logic), iterate `$metadata->extensions`
- For each `ExtensionMetadata`:
  1. Create a new instance of the extension class via `ReflectionClass::newInstanceWithoutConstructor()`
  2. For each property in `ExtensionMetadata::$properties`, look up the column name, read the value from `$row`, convert to PHP type via the existing private `convertToPhpType()`, and set the property via reflection
  3. **Partial-row handling**: if NONE of the extension's column names are present in `$row`, skip hydrating that extension entirely (do not attach it). If SOME are present and others are not, hydrate the present ones and leave the missing ones uninitialised (matching base-entity hydrator behaviour at line 53 of `EntityHydrator.php`, which uses `continue` when a column is missing).
  4. Call `$entity->setExtension($extensionInstance)` to attach
- Original values tracking (for dirty detection) does NOT apply to extension instances in this iteration — do not write to `$this->originalValues` for extension property values

## Requirements (Test Descriptions)
- [ ] `it attaches a hydrated extension instance to the entity when its columns are in the row`
- [ ] `it hydrates extension property values from the DB row`
- [ ] `it skips attaching an extension when none of its columns are present in the row`
- [ ] `it partially hydrates an extension when only some of its columns are present in the row`
- [ ] `it hydrates multiple extensions from the same row independently`
- [ ] `it converts extension column values to the correct PHP types`
- [ ] `it converts nullable extension column values to null`
- [ ] `it converts JSON extension column values to arrays`
- [ ] `it converts BackedEnum extension column values via the enum class`
- [ ] `it does not affect base entity hydration when no extensions are registered`
- [ ] `it does not include extension property values in the originalValues dirty-tracking map`

## Acceptance Criteria
- All requirements have passing tests
- Existing hydrator tests continue to pass
- Code follows project standards
