# Task 038: Remove `ScopedOverridesEntity` — Single-Approach Cleanup

**Status**: completed
**Depends on**: [033, 034, 035, 036, 037]
**Retry count**: 0

## Description
Delete `ScopedOverridesEntity` entirely and consolidate on `HasScopes` + `HasScopesInterface` as the sole scope storage mechanism. Companion classes (for third-party entities) are still supported — users declare them manually using the trait. `ScopeResolver::createCompanion()` is removed; `ScopedEntityValidator` generalises to accept any `HasScopesInterface` implementor (entity-self or companion) rather than requiring a `ScopedOverridesEntity` subclass specifically.

## Context

### Why removing `ScopedOverridesEntity` is safe
- `HasScopes` trait provides identical behaviour — every project that used `ScopedOverridesEntity` as a companion base can replace it with a plain class that `extends Entity`, `use HasScopes`, `implements HasScopesInterface`.
- The companion lookup in `ScopeResolver::findStorage()` already uses `instanceof HasScopesInterface`, not `instanceof ScopedOverridesEntity` — no resolver logic changes for the happy path.
- Companion use case (adding scopes to a third-party entity) is preserved; users just write two more lines.

### Changes required

**Delete:**
- `packages/scope/src/Storage/ScopedOverridesEntity.php`
- All tests referencing `ScopedOverridesEntity` as a concrete class to instantiate (replace fixtures with trait-based equivalents or plain companions using the trait)
- `packages/scope/tests/Unit/Storage/ScopedOverridesEntityTest.php` — test for a class that no longer exists; delete the file

**`packages/scope/src/Resolver/ScopeResolver.php`:**
- Remove `createCompanion()` method entirely
- Update `setOverride()`: when `findStorage()` returns `null`, throw `ScopeContextException` immediately (no companion auto-creation). The error message should say the entity must implement `HasScopesInterface` or have a companion that does.
- Remove the `use Marko\Scope\Storage\ScopedOverridesEntity;` import (was kept only for `createCompanion()`)

**`packages/scope/src/Validation/ScopedEntityValidator.php`:**
- Remove the `wrongOverridesExtenderBase` path — it was specific to detecting non-`ScopedOverridesEntity` companion bases, which no longer matters
- Remove the `traitAndCompanionConflict` path — the conflict only arose when both the trait AND a `ScopedOverridesEntity` extender contributed a `scopes` column; without the class, the conflict is impossible
- New validation logic (3 branches, in order):
  1. Entity has no `#[Scoped]` properties → return (valid, unchanged)
  2. `is_a($entityClass, HasScopesInterface::class, true)` → return (valid — entity is its own storage)
  3. Entity has at least one registered companion that `implements HasScopesInterface` → return (valid — companion provides storage)
  4. Otherwise → throw `ScopeConfigurationException::missingScopesStorage($entityClass)` (see below)
- Remove the `use Marko\Scope\Storage\ScopedOverridesEntity;` import

**`packages/scope/src/Exceptions/ScopeConfigurationException.php`:**
- Remove `wrongOverridesExtenderBase()` factory (no longer reachable)
- Remove `traitAndCompanionConflict()` factory (no longer reachable)
- Rename `missingOverridesExtender()` → `missingScopesStorage()` with an updated message: `"Entity '{$entityClass}' has #[Scoped] properties but provides no scope storage. Add 'use HasScopes; implements HasScopesInterface;' to the entity, or register a companion class that implements HasScopesInterface."`
- Keep `message`, `context`, `suggestion` named-parameter pattern

**`packages/scope/README.md`:**
- Remove "Alternative: companion class" section that references `ScopedOverridesEntity`
- Add a replacement section "Companion class (for third-party entities)" showing the manual pattern:
  ```php
  #[Table(extends: Product::class)]
  class ProductScopedOverrides extends Entity implements HasScopesInterface
  {
      use HasScopes;
  }
  ```
- Update "Don't mix them" warning: mixing is now impossible (no `ScopedOverridesEntity`); replace with a note that a companion providing `HasScopesInterface` and the entity itself implementing `HasScopesInterface` is valid but the entity-self path wins
- Update "Batch insert" note: trait-based entities are compatible; companion-based entities are not

**Docs pages** (`docs/src/content/docs/packages/scope.md`, `scope-mysql.md`, `scope-pgsql.md`): update to reflect removal.

### Backwards compatibility note
This is a breaking change for anyone who subclassed `ScopedOverridesEntity`. Migration is mechanical: replace `extends ScopedOverridesEntity` with `extends Entity implements HasScopesInterface { use HasScopes; }`. Since the project is pre-1.0, this is acceptable.

## Requirements (Test Descriptions)
- [ ] `it accepts a trait-based entity as valid scopes storage (HasScopesInterface on entity)`
- [ ] `it accepts a companion that implements HasScopesInterface as valid scopes storage`
- [ ] `it accepts an entity with no scoped properties regardless of storage`
- [ ] `it throws ScopeConfigurationException missingScopesStorage when entity has scoped properties but no storage`
- [ ] `the missingScopesStorage exception message names the entity class and describes both remedies`
- [ ] `ScopeResolver setOverride throws ScopeContextException when entity has no HasScopesInterface and no compatible companion`
- [ ] `ScopeResolver setOverride works on a trait-based entity`
- [ ] `ScopeResolver setOverride works when a manual HasScopesInterface companion is attached`
- [ ] `ScopeResolver resolved works with a manual HasScopesInterface companion (not ScopedOverridesEntity)`
- [ ] `ScopeResolver does not have a createCompanion method`

## Acceptance Criteria
- `ScopedOverridesEntity.php` deleted; `ScopedOverridesEntityTest.php` deleted
- No reference to `ScopedOverridesEntity` remains in `src/` or `tests/`
- `createCompanion()` removed from `ScopeResolver`
- `ScopedEntityValidator` passes for `HasScopesInterface` entity and for any `HasScopesInterface` companion
- `ScopeConfigurationException` has `missingScopesStorage()` instead of `missingOverridesExtender()`, `wrongOverridesExtenderBase()`, `traitAndCompanionConflict()`
- All tests passing
- README and docs updated

## Implementation Notes
(Left blank - filled in by programmer during implementation)
