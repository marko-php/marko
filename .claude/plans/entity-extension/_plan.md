# Plan: Entity Extension

## Created
2026-05-11

## Status
completed

## Objective
Allow any module to add columns to an existing entity's table without modifying the entity class. Extensions are discovered automatically, merged into the entity's schema at boot, hydrated transparently from the same DB row, and accessed via a typed `extension()` method that IDEs understand via `@template`.

## Related Issues
none

## Discovery Notes
The current entity system is entirely reflection-based: `EntityMetadataFactory` reads `#[Column]` from declared public properties; `EntityHydrator` sets those properties via reflection; `Repository` types everything against a compile-time `ENTITY_CLASS` constant. All data lives in one table per entity.

Extension columns must also live in the same table (same-table strategy; separate-table is out of scope). Since `__get`/`__set` are forbidden, extended data cannot be accessed as direct properties on the base entity — instead, a typed `extension()` accessor is used. The `@template` annotation on that method gives IDEs full type resolution without any code generation.

`ProjectPaths` (already in the container) provides vendor/modules/app path strings, matching the pattern used by `SeederRunner` wiring in `database/module.php`.

`SchemaBuilder.build()` iterates `metadata->columns` directly — it only needs to be taught to also iterate extension columns from a new `extensions` field on `EntityMetadata`.

## Scope

### In Scope
- `EntityExtension` abstract base class
- `#[ExtensionOf]` attribute declaring which entity class an extension targets
- `ExtensionMetadata` value object (properties + columns, no table/PK)
- `EntityExtensionMetadataFactory` — parses extension classes
- `EntityExtensionRegistry` — singleton, maps entity class → list of extension classes
- `EntityExtensionDiscovery` — scans `src/EntityExtension/` directories in vendor/modules/app
- `Entity` gains private `$extensions` bag, `extension()` generic accessor, `setExtension()`
- `EntityMetadata` gains `extensions: array<class-string, ExtensionMetadata>` field
- `EntityMetadataFactory` injects registry, merges extension metadata, detects column conflicts
- `EntityHydrator` hydrates extension objects from same DB row, attaches to entity
- `Repository::insert()` and `update()` include extension columns (null/default/error logic)
- `SchemaBuilder::build()` includes extension columns in the table schema
- Boot wiring in `database/module.php` — registry populated via discovery at boot

### Out of Scope
- Separate-table storage for extension data
- Dirty-checking on extension properties (updates always write all extension columns)
- Relationships on extension classes
- Indexes declared on extension classes

## Success Criteria
- [ ] Any module can declare an `EntityExtension` subclass in `src/EntityExtension/` and have its columns auto-discovered and merged into the target entity's table schema
- [ ] Repositories hydrate extensions transparently from the same DB row with no explicit `with()` call
- [ ] `$entity->extension(MyExtension::class)` returns `MyExtension|null`, IDE infers the return type
- [ ] Saving an entity without an extension attached: nullable columns → NULL, declared defaults → default value, non-nullable with no default → loud exception
- [ ] Column name collision between two extensions on the same entity → loud exception at boot
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | EntityExtension base class + `#[ExtensionOf]` attribute | - | completed |
| 002 | ExtensionMetadata + EntityExtensionMetadataFactory | 001 | completed |
| 003 | EntityExtensionRegistry + EntityExtensionDiscovery | 001, 002 | completed |
| 004 | Entity: extension bag, `extension()`, `setExtension()` | 001 | completed |
| 005 | EntityMetadata: extensions field + EntityMetadataFactory: merge extensions | 002, 003 | completed |
| 006 | EntityHydrator: hydrate extensions from same DB row | 004, 005 | completed |
| 007 | Repository: include extension columns in INSERT/UPDATE | 005, 006 | pending |
| 008 | SchemaBuilder: include extension columns in table schema | 005 | pending |
| 009 | Boot wiring: populate registry via discovery in module.php | 003 | completed |
| 010 | README | 007, 008, 009 | completed |

## Architecture Notes
- Extension columns live in the base entity's table — `SELECT *` returns them automatically, so extensions are always available on fetched entities (no lazy-loading concept needed).
- `EntityExtensionRegistry` must be a singleton (wired in `module.php` `singletons`). It is populated in the `boot` callback before any repository is used.
- `EntityMetadataFactory` already caches by class; merging extensions into the cache means the overhead is paid exactly once per entity class per process.
- UPDATE always writes all extension columns (no dirty-checking on extensions) to avoid needing a parallel WeakMap for extension original values. This is a known limitation and acceptable for the initial implementation.
- The `#[ExtensionOf]` attribute argument cannot be validated at attribute-definition time (PHP attributes are lazy); validation happens in `EntityExtensionDiscovery` when the class is discovered.
- **Backward compatibility for `EntityMetadataFactory`**: the factory's new constructor parameters (`EntityExtensionRegistry`, `EntityExtensionMetadataFactory`) are both nullable with `null` defaults. This keeps existing test code that does `new EntityMetadataFactory()` working (26+ test files in `packages/database/tests/` and `packages/admin-auth/tests/`). Container-injected instances get real dependencies; direct test instantiation falls back to "no extensions" behaviour.
- **`EntityMetadata` is `readonly`**: the new `extensions` field is added as the LAST constructor parameter with default `[]` so all existing call-sites continue to work without modification.
- **`Repository::update()` early-return**: the current `if (count($dirtyProperties) === 0) return;` short-circuit must be updated to also account for extension columns; otherwise saves on clean base entities with extensions never persist.
- **`Repository::insertBatch()` column-set consistency**: the batch path verifies all rows have the same column set via `array_keys`. Extension columns are determined by metadata, not per-entity attachment, so column sets remain consistent — but the null/default/error policy is applied per-row and can fail for individual rows independently.
- **Discovery method return shape**: `EntityExtensionDiscovery::discoverIn*` returns `array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>` (list of pairs); both task 003 (definition) and task 009 (consumption) agree on this shape.
- **`EntityHydrator::convertToPhpType` reuse**: kept `private`; extension hydration code path lives inside `EntityHydrator` and calls the private method directly. No visibility change required.

## Risks & Mitigations
- Extension columns conflicting with base entity columns: detected and thrown as a loud exception in `EntityMetadataFactory` when merging.
- Extension columns conflicting with other extension columns: same detection pass.
- Extension property names conflicting with base entity property names (separate from column names): same detection pass.
- Registry not yet populated when MetadataFactory is called: registry is a singleton populated in boot before the first repository resolution; unit tests wire the registry explicitly or pass `null` to the factory constructor for backward-compat.
- Constructor change to `EntityMetadataFactory` breaking 26+ test sites: mitigated by nullable defaults on the new parameters.
- Extension class on entity that hasn't been migrated yet (column missing in `$row`): hydrator skips that extension entirely if NO columns are present, or partially hydrates if SOME are present (matching base-entity behaviour); SAVE-time policy applies independently.
