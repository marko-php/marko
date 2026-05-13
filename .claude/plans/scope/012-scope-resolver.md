# Task 012: `ScopeResolver` service

**Status**: pending
**Depends on**: 008, 010, 011
**Retry count**: 0

## Description
The app-facing API for resolving and mutating scoped attributes on entities. Combines metadata + walker + override mutations. This is the primary surface app code uses; the hydration/save plugins (tasks 013/014) sit underneath it.

## Context
- Related files: `packages/scope/src/Resolver/ScopeResolver.php` (new)
- Patterns to follow: Plain service, constructor injection of `ScopeMetadataFactory`, `ScopeWalker`, `ScopeContext`. No state.

## Requirements (Test Descriptions)
- [ ] `it resolves a property value via current ScopeContext returning the walker match`
- [ ] `it falls back to the entity's column property value when no override is found`
- [ ] `it resolves at an explicit scope via resolvedAt without consulting ScopeContext`
- [ ] `it sets an override via setOverride attaching a ScopedOverridesEntity companion if missing`
- [ ] `it sets an override on a new (unsaved) entity then saves so both rows reflect the override` (feature)
- [ ] `it clears an override via clearOverride leaving the companion otherwise intact`
- [ ] `it throws ScopeContextException when resolving an unknown property`
- [ ] `it throws ScopeContextException when setOverride targets a property without Scoped`
- [ ] `it discovers the correct ScopedOverridesEntity subclass for a given parent entity class via EntityMetadata::extenders`

## Acceptance Criteria
- `readonly class` — no mutable state.
- Constructor takes `ScopeMetadataFactory`, `ScopeWalker`, `ScopeContext`, and `EntityMetadataFactory` (the last is used to find the correct `ScopedOverridesEntity` subclass via `EntityMetadata::extenders`).
- `resolved(Entity $entity, string $property): mixed` reads ScopeContext.
- `resolvedAt(Entity $entity, string $property, Scope $scope): mixed` builds a one-shot context view of `$scope`.
- `setOverride` / `clearOverride` validate that `$scope`'s axis is among the property's declared axes.
- `setOverride` on an entity without an attached companion instantiates the correct `ScopedOverridesEntity` subclass (looked up via `EntityMetadata::extenders` filtered by `is_subclass_of(..., ScopedOverridesEntity::class)`), attaches it via `$entity->attachCompanion()`, and writes the override. The parent's PK does not need to exist yet — `Repository::insert` will handle the companion at save time.

## Implementation Notes
(Left blank — filled in during implementation.)
