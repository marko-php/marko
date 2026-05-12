# Plan: Table Extension via #[Table(extends:)]

## Created
2026-05-12

## Status
completed

## Objective
Allow any module to add columns to an existing entity's table without touching the original entity class, by extending the existing `#[Table]` attribute with an `extends:` parameter on plain `Entity` subclasses. Reuses the existing Entity pipeline instead of building a parallel `EntityExtension` hierarchy.

## Related Issues
Closes #63

## Discovery Notes

- **Schema today** is derived directly from `#[Column]` attributes on entity properties. `SchemaRegistry::registerEntity()` calls `EntityMetadataFactory::parse()` → `SchemaBuilder::build()`. There is no standalone "schema file" concept.
- **Existing pipeline** to reuse: `EntityDiscovery` (no changes needed; finds any class with `#[Table]`), `EntityMetadataFactory` (extend with extension validation + name resolution from parent), `EntityHydrator` (extend with companion hydration), `Repository` (extend `insert`/`update` to merge extender columns), `SchemaRegistry` (two-pass registration: collect all, then merge).
- **Registration order matters**: an extender's table name comes from the parent's `#[Table(name:)]`, so the parent must be parsed first OR registration must be two-pass. We'll use two-pass inside `SchemaRegistry::registerEntities()`: first pass collects metadata and separates parents from extenders; second pass builds parent tables and merges extender columns/indexes/foreign-keys into them.
- **Cache coherence**: after the two-pass merge, the parent's `EntityMetadata` instance gets `$extenders` populated. This updated instance MUST replace the cached copy inside `EntityMetadataFactory` so that any later `parse()` call (e.g. from `Repository::__construct`) returns metadata that knows about extenders. SchemaRegistry calls a new `EntityMetadataFactory::replace(class-string, EntityMetadata)` (or `linkExtenders()`) to keep the cache coherent.
- **Conflict policy**: two extenders adding a column with the same name → loud error at boot pointing at both classes. Column-name vs. parent-column conflict → same loud error.
- **Rolling-deploy safety**: at hydration time, if columns for a registered extender aren't present in the row (DB schema not yet migrated), silently skip hydrating that extender. Already-migrated extenders still hydrate.
- **`Entity::companions()`** stores hydrated extender instances via the same `WeakMap` pattern `EntityHydrator` already uses for `originalValues`. Keeps `Entity` itself stateless (still no properties on the base class beyond what's needed).
- **PR #59 is closed and superseded.** That PR's validation/conflict/rolling-deploy logic is the reference behavior; this plan re-implements it on the smaller surface.

## Scope

### In Scope
- New `extends: ?class-string $extends = null` param on `#[Table]`; `name:` becomes optional when `extends:` is set
- `EntityMetadata::$extends` (parent class-string) and `EntityMetadata::$extenders` (list of extender class-strings)
- `EntityMetadataFactory`: parse and validate `extends:`, resolve table name from parent, link extenders to parent metadata, enforce constraints
- `SchemaRegistry`: two-pass registration that merges extender columns/indexes/foreign-keys into parent's `Table`; detect column-name AND index-name conflicts loudly; write the linked metadata back into `EntityMetadataFactory`'s cache for coherence
- `EntityHydrator`: hydrate companion (extender) entities from the same row; silently skip extenders whose columns are missing
- `Repository::insert()` / `Repository::update()`: merge extender columns from companions into a single SQL statement (via new `EntityHydrator::extractAll()`); `Repository::__construct()` rejects extender ENTITY_CLASS; `Repository::insertBatch()` rejects entities with companions attached
- `Entity::companions()`, `Entity::companion(class-string<T>)`, and public `Entity::attachCompanion(Entity)` accessor APIs with `@template T` for IDE inference; companion attach mechanism via shared WeakMap (hydrator owns storage; both the hydrator-internal attach used at hydrate time and the public `Entity::attachCompanion()` used at user-write time write into the same map)
- Validation errors via new `EntityException` factory methods (loud at boot or first-parse)

### Out of Scope
- Cross-table extensions (FK to a separate table) — that's a normal relationship
- Removing columns added by another module — modules own their own writes
- Extender-level relationships against the parent (relationships are first-class on extenders themselves; this plan doesn't add anything new there)
- Migration generation for adding/dropping extension columns — `migrate:diff` already operates on the merged `Table`, so this falls out for free; no new code, but worth verifying in the integration task
- Performance optimization beyond "one SELECT" for hydration
- Per-extender enable/disable flags

## Success Criteria
- [ ] Declaring `#[Table(extends: ParentEntity::class)]` on a `class Foo extends Entity` registers Foo's columns into ParentEntity's table
- [ ] `Repository::find()` on the parent returns the parent entity with companions populated
- [ ] `Repository::save()` on the parent persists parent + companion columns in a single INSERT/UPDATE
- [ ] `migrate:diff` sees the merged table (proven by integration test)
- [ ] Invalid extender configurations (own `name:`, redeclared PK, `autoIncrement: true`, duplicate column name) fail loudly with helpful errors
- [ ] Rolling-deploy safe: extender hydration silently skipped when its columns aren't in the row
- [ ] All tests passing (`composer test`)
- [ ] Code follows project standards (php-cs-fixer clean, phpcs clean)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `extends:` param to `#[Table]` attribute | - | completed |
| 002 | Add `extends`/`extenders` fields to `EntityMetadata` | - | completed |
| 003 | `Entity::companions()` accessor + `EntityHydrator` companion storage | - | completed |
| 004 | `EntityMetadataFactory` validation & extender linking | 001, 002 | completed |
| 005 | `SchemaRegistry` two-pass merge & conflict detection | 004 | completed |
| 006 | `EntityHydrator` companion hydration with rolling-deploy skip | 003, 004 | completed |
| 007 | `Repository` INSERT/UPDATE extender column merge | 004, 006 | completed |
| 008 | End-to-end integration test (parent + extender) | 005, 006, 007 | completed |

## Architecture Notes

- **One concept, not two.** Extenders are plain `Entity` subclasses. Reuse `EntityDiscovery`, `EntityMetadataFactory`, `EntityHydrator`, `Repository`. No `EntityExtension` base class, no `#[ExtensionOf]` attribute, no parallel registry/discovery.
- **`extends:` is a verb param**, parallel to `#[Preference(replaces:)]`. Reads as "this table extends ParentEntity('s table)."
- **Companion storage uses `WeakMap`**, matching the existing `EntityHydrator::$originalValues` pattern. Keeps `Entity` base class free of state.
- **Two-pass schema registration** isolates the dependency-order problem inside `SchemaRegistry::registerEntities()`. Callers don't have to register parents first.
- **Single SELECT for hydration**: when loading a parent, `SELECT *` already returns extender columns. Hydrator instantiates companions from the same row — no extra queries.
- **Single INSERT/UPDATE for writes**: when saving a parent, hydrator's `extract` is extended (via a new `extractAll()` helper) to include attached companions' columns. Companion entities do NOT get their own `Repository` — extenders have no primary key of their own, so a standalone repository is invalid. `Repository::__construct()` raises a loud error if `ENTITY_CLASS` resolves to an extender.
- **Public companion-attach API**: user code attaches a companion via `Entity::attachCompanion(Entity $companion)` (delegates into the hydrator's WeakMap) so brand-new entities can carry companions before the first save. Hydrator's internal `attachCompanion()` is used during read; the public `Entity::attachCompanion()` is used during write.
- **insertBatch with companions is out of scope for v1**: `Repository::insertBatch()` raises a loud error if any entity in the batch has an attached companion. Single-row `insert()` / `update()` handle companions in v1.

## Risks & Mitigations

- **Risk:** Companion attached to one Entity instance accidentally leaks across instances → **Mitigation:** Use `WeakMap` keyed by Entity instance, never store on the class.
- **Risk:** A user puts `name:` AND `extends:` on the same `#[Table]` → **Mitigation:** Loud validation in `EntityMetadataFactory`.
- **Risk:** Circular extension chains (A extends B, B extends A) → **Mitigation:** Validate in `EntityMetadataFactory` that the parent class itself does not have `extends:` set. (Multi-level extension is out of scope for v1.)
- **Risk:** Conflicting column names between extenders introduced at boot but only surfaced at first `parse()` call → **Mitigation:** Run conflict detection inside `SchemaRegistry::registerEntities()` after the second pass, with both class-strings in the error message.
- **Risk:** Update path tries to UPDATE columns from an extender whose row columns are missing in the DB (rolling deploy) → **Mitigation:** During `update`, skip extender column writes if the column was never hydrated for this entity (mirrors the read-side skip).
- **Risk:** Auto-increment PK conflict if an extender redeclares the PK property → **Mitigation:** Loud validation; extenders may not declare a `primaryKey:true` column.
- **Risk:** Stale metadata in `EntityMetadataFactory` cache after `withExtenders()` merge — `Repository::__construct` would receive a parent metadata with empty `$extenders` → **Mitigation:** `SchemaRegistry` writes the linked metadata back into the factory's cache via a new `replace()`/`linkExtenders()` method.
- **Risk:** `insertBatch()` silently drops companion columns or trips `columnSetMismatch` → **Mitigation:** Loud error in `insertBatch()` when entities have companions attached (out of scope for v1, see Architecture Notes).
- **Risk:** User creates a Repository against an extender class, hits cryptic `MissingPrimaryKeyException` → **Mitigation:** `Repository::__construct` raises a clear error if the metadata's `isExtender()` is true.
- **Risk:** Foreign keys declared on an extender's columns are silently dropped by the merge → **Mitigation:** Two-pass merge in `SchemaRegistry` includes FK merge via `Table::withForeignKey()`.
