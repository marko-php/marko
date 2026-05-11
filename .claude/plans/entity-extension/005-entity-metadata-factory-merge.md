# Task 005: EntityMetadata extensions field + EntityMetadataFactory merge

**Status**: completed
**Depends on**: [002, 003]
**Retry count**: 0

## Description
Add an `extensions` field to `EntityMetadata` (a map of extension class → `ExtensionMetadata`) and update `EntityMetadataFactory` to query `EntityExtensionRegistry` when parsing an entity, building and attaching `ExtensionMetadata` for each registered extension. Detect and loudly report column name conflicts between the base entity and its extensions, or between two extensions.

## Context
- Related files:
  - `packages/database/src/Entity/EntityMetadata.php` (currently `readonly class`)
  - `packages/database/src/Entity/EntityMetadataFactory.php`
  - `packages/database/src/Entity/ExtensionMetadata.php` (from task 002)
  - `packages/database/src/Entity/EntityExtensionRegistry.php` (from task 003)
  - `packages/database/src/Entity/EntityExtensionMetadataFactory.php` (from task 002)
  - `packages/database/tests/Entity/EntityMetadataTest.php` — may need updating
  - `packages/database/tests/Entity/EntityMetadataFactoryTest.php` — instantiates `new EntityMetadataFactory()`; must continue to work
  - `packages/database/tests/Entity/EntityMetadataFactoryRelationshipTest.php` — instantiates `new EntityMetadataFactory()`; must continue to work
  - All 26+ test files that call `new EntityMetadataFactory()` (search for the pattern across the monorepo before editing)
- `EntityMetadata` gains a constructor parameter and property `public array $extensions = []` typed as `array<class-string<EntityExtension>, ExtensionMetadata>`, default empty. Because `EntityMetadata` is declared `readonly`, the field MUST be added as the LAST constructor parameter with a default of `[]` so all existing call-sites (`new EntityMetadata(entityClass: ..., tableName: ..., ...)`) continue to compile. Add appropriate phpdoc.
- `EntityMetadataFactory` gains a constructor dependency on `EntityExtensionRegistry` and `EntityExtensionMetadataFactory`. **BREAKING-CHANGE MITIGATION**: both new constructor parameters MUST be nullable with a default of `null`. When `null`, the factory behaves as before (no extensions merged). The container will still inject real instances in production (because both are autowirable); only test code that does `new EntityMetadataFactory()` without args remains green without modification. Document this explicitly in a class-level comment.
- After parsing the base entity properties, IF `$this->registry !== null && $this->extensionMetadataFactory !== null`, the factory calls `$this->registry->getExtensions($entityClass)`, parses each via `EntityExtensionMetadataFactory::parse()`, and builds an `array<class-string<EntityExtension>, ExtensionMetadata>` keyed by extension class name; this is passed to the `EntityMetadata` constructor.
- Column conflict detection: collect all column names (base + all extensions); if any column name appears more than once, throw `EntityException` (new factory method: `extensionColumnConflict(string $entityClass, string $columnName, string $sourceA, string $sourceB)`) naming both the base/extension sources and the conflicting column name. `$sourceA`/`$sourceB` are either the entity class or an extension class.
- Property NAME collisions are also possible (two extensions could declare the same public property name even if their column names differ). Detect and throw the same exception class (new factory method: `extensionPropertyConflict`).
- The metadata cache must be invalidated/rebuilt if extensions are added after first parse — in practice the registry is fully populated before any parse call (boot ordering, task 009), so this is safe. Do not implement cache invalidation, but add a comment to that effect on the cache field.

## Requirements (Test Descriptions)
- [ ] `it includes an empty extensions map by default on EntityMetadata`
- [ ] `it constructs EntityMetadataFactory with null registry and factory for backward compatibility`
- [ ] `it populates extensions from the registry when parsing an entity with registered extensions`
- [ ] `it leaves extensions empty when no extensions are registered for the entity`
- [ ] `it leaves extensions empty when constructed without a registry`
- [ ] `it throws when an extension column name collides with a base entity column name`
- [ ] `it throws when two extension column names collide with each other`
- [ ] `it throws when an extension property name collides with a base entity property name`
- [ ] `it caches entity metadata including extensions`

## Acceptance Criteria
- All requirements have passing tests
- Existing `EntityMetadataTest` and `EntityMetadataFactoryRelationshipTest` tests continue to pass without modification
- All 26+ test files that instantiate `new EntityMetadataFactory()` directly continue to compile and pass
- New factory methods added to `Marko\Database\Exceptions\EntityException`:
  - `extensionColumnConflict(string $entityClass, string $columnName, string $sourceA, string $sourceB)`
  - `extensionPropertyConflict(string $entityClass, string $propertyName, string $sourceA, string $sourceB)`
- Code follows project standards
