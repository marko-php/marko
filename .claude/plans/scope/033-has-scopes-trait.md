# Task 033: `HasScopesInterface` + `HasScopes` Trait

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Introduce `HasScopesInterface` as the contract for scope override storage, and `HasScopes` as a trait that implements it. Entities `use HasScopes` instead of declaring a separate `ScopedOverridesEntity` companion class. `ScopedOverridesEntity` is updated to `implements HasScopesInterface` so both paths remain in use without breaking existing code.

## Context
- The existing `ScopedOverridesEntity` already has `setOverride`, `getOverride`, `hasOverride`, `clearOverride`, `allOverrides`, and `#[Column(type: 'json', nullable: true)] public ?array $scopes = null`. The trait extracts these into a reusable form.
- PHP's `ReflectionClass::getProperties()` includes trait properties, so `EntityMetadataFactory` will pick up the `#[Column]` on `$scopes` automatically — no framework change needed for schema.
- The interface cannot be declared inside the trait (`trait T implements I` is not valid PHP). Entities that `use HasScopes` must also `implements HasScopesInterface` in their class declaration. Document this in a class-level docblock on the trait.
- The trait is only useful when used on an `Marko\Database\Entity\Entity` subclass — the `#[Column]` attribute on `$scopes` is only meaningful when `EntityMetadataFactory` parses the class. Document this constraint in the trait docblock.
- `HasScopesInterface` MUST declare all five methods (`setOverride`, `getOverride`, `hasOverride`, `clearOverride`, `allOverrides`). Even though `ScopeWalker`/`ScopeResolver` do not call `allOverrides()`, keeping it on the interface preserves parity with the existing `ScopedOverridesEntity` public API and supports debug / diff / serialization helpers that work polymorphically against the interface.
- Test fixture pattern for `HasScopesTraitTest.php`: declare a small `#[Table(name: '...')]` class that `extends Entity`, declares `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;`, `use HasScopes;`, and `implements HasScopesInterface`. This fixture is reused in tasks 034/035/036 tests.
- Related files:
  - `packages/scope/src/Storage/ScopedOverridesEntity.php` — add `implements HasScopesInterface`
  - `packages/scope/src/Storage/HasScopesInterface.php` — new file
  - `packages/scope/src/Storage/HasScopes.php` — new file
  - `packages/scope/tests/Unit/Storage/ScopedOverridesEntityTest.php` — add interface-assertion test
  - `packages/scope/tests/Unit/Storage/` — new `HasScopesTraitTest.php`

## Requirements (Test Descriptions)
- [x] `it exposes setOverride getOverride hasOverride clearOverride allOverrides via the trait`
- [x] `it stores multiple overrides keyed by scopeKey and property`
- [x] `it returns null for unknown scopeKey or property via getOverride`
- [x] `it returns false for hasOverride when no override exists`
- [x] `it distinguishes an explicit null override from no override via hasOverride`
- [x] `it clears a single property override leaving others intact`
- [x] `it sets scopes to null when the last override is cleared`
- [x] `it declares a json nullable Column attribute on the scopes property when reflected via a consuming class`
- [x] `a class using HasScopes can satisfy the HasScopesInterface contract`
- [x] `ScopedOverridesEntity implements HasScopesInterface and its public method signatures match the interface`
- [x] `HasScopesInterface declares setOverride getOverride hasOverride clearOverride allOverrides`

## Acceptance Criteria
- All requirements have passing tests
- `HasScopesInterface` declares all five storage methods: `setOverride`, `getOverride`, `hasOverride`, `clearOverride`, `allOverrides`
- `HasScopes` trait body is identical in behaviour to the equivalent methods already in `ScopedOverridesEntity`
- `ScopedOverridesEntity` test suite still fully passes after adding `implements HasScopesInterface`
- Trait carries a class-level docblock noting (a) consumers must declare `implements HasScopesInterface` because PHP cannot enforce it from inside a trait, and (b) the trait is intended for use on `Marko\Database\Entity\Entity` subclasses so the `#[Column]` attribute is picked up by `EntityMetadataFactory`
- Code follows project standards

## Implementation Notes
- Created `HasScopesInterface` with all five methods (`setOverride`, `getOverride`, `hasOverride`, `clearOverride`, `allOverrides`)
- Created `HasScopes` trait with methods extracted from `ScopedOverridesEntity`; trait carries docblock noting consumers must `implements HasScopesInterface` and should extend `Entity`
- Updated `ScopedOverridesEntity` to `implements HasScopesInterface`
- Added `HasScopesTraitTest.php` with 11 tests; `TraitProduct` fixture reused in tasks 034/035/036
- Added interface-assertion test to `ScopedOverridesEntityTest.php`
- All 141 scope tests pass; sole pre-existing `EnvLoaderTest` failure is baseline noise unrelated to this task
