# Task 010: `ScopedOverridesEntity` base class (entity extender)

**Status**: pending
**Depends on**: 002, 009
**Retry count**: 0

## Description
The per-entity override container, implemented as an abstract base `Entity` subclass intended to be extended once per scoped parent entity via the existing `marko/database` extender mechanism (`#[Table(extends: Parent::class)]`). Stores a single JSON column `scopes` on the parent's table (no JOIN required, mirroring the `TimestampsExtender` pattern). Dirty tracking, INSERT, and UPDATE all flow through the existing `Repository` companion path — no custom save plugin is needed.

App developers declare one companion class per scoped parent entity, e.g.:

```php
#[Table(extends: Product::class)]
class ProductScopedOverrides extends ScopedOverridesEntity {}
```

The base class supplies the `#[Column(name: 'scopes', type: 'json', nullable: true)]` array property and the override accessors. Subclasses add only the `#[Table(extends: ...)]` attribute.

## Context
- Related files: `packages/scope/src/Storage/ScopedOverridesEntity.php` (new), `packages/database/src/Entity/Entity.php` (read-only — extender mechanism in `EntityMetadataFactory` / `SchemaRegistry`)
- Patterns to follow: Extender pattern — see `packages/database/src/Attributes/Table.php`, `EntityMetadataFactory::linkExtenders`, and `Repository::update` companion dirty-tracking (lines 632-700). The base class declares the column; subclasses declare the parent linkage.

## Data Shape Contract
Internal storage of the JSON column is a flat map keyed by scope key first, property second:

```php
// $this->scopes maps to:
[
    'geo:eu.de'  => ['name' => 'Hemd', 'price' => 19.99],
    'locale:de'  => ['name' => 'Hallo'],
]
```

This shape is shared with `ScopedDataSerializer` (task 009), `ScopeWalker` (task 011), and the SQL renderers (tasks 015, 019, 023). Scope-key-first ordering enables O(1) lookups during walker resolution.

## Requirements (Test Descriptions)
- [ ] `it is an abstract Entity subclass with a Scoped column named scopes typed as json`
- [ ] `it stores an override keyed by scope key and property via setOverride`
- [ ] `it returns the stored override via getOverride for the same property and scope`
- [ ] `it returns null from getOverride when no override exists at that scope`
- [ ] `it removes an override via clearOverride and getOverride returns null afterward`
- [ ] `it removes the entire scope-key sub-map when its last property is cleared`
- [ ] `it distinguishes an explicit null override from no override via hasOverride`
- [ ] `it lists all overrides via allOverrides as the flat scope-key-first map`
- [ ] `it functions as a companion attached via Entity::attachCompanion and is retrievable via Entity::companion`
- [ ] `it participates in Repository::save dirty tracking via the existing companion path` (feature test)

## Acceptance Criteria
- Class is `abstract class ScopedOverridesEntity extends Entity`.
- Declares a single `#[Column(name: 'scopes', type: 'json', nullable: true)] public ?array $scopes = null;` property whose value follows the Data Shape Contract above.
- Accessor methods (`setOverride`, `getOverride`, `clearOverride`, `hasOverride`, `allOverrides`) operate on `$this->scopes` directly so that the existing `EntityHydrator::getDirtyProperties` flow detects changes.
- App-developer subclasses only need `#[Table(extends: ParentEntity::class)]` to wire into the parent.
- No `markClean`/baseline snapshotting — dirty tracking is delegated to `EntityHydrator::originalValues` via the existing companion path.

## Implementation Notes
- **Dirty-tracking gotcha:** `EntityHydrator::valuesEqual` uses strict `===` for non-DateTime / non-Enum values. PHP arrays are `===` only when keys/values are in the same insertion order. Mutating `$this->scopes` via `setOverride` may reorder keys. To keep dirty tracking accurate, `setOverride` / `clearOverride` MUST canonicalise the scope-key ordering (e.g. ksort the top-level keys, ksort the inner property arrays) on every mutation. The serializer (task 009) should canonicalise on the way out as well so JSON in the database is deterministic.
