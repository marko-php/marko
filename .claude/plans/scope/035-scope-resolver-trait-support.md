# Task 035: Update `ScopeResolver` for Trait-Based Entities

**Status**: pending
**Depends on**: [033, 034]
**Retry count**: 0

## Description
Update `ScopeResolver` so that when an entity itself implements `HasScopesInterface` (via the `HasScopes` trait), the resolver uses the entity directly as the override storage — no companion lookup or creation needed. The companion-based path remains intact as a fallback so existing code is not broken.

## Context
- Related files:
  - `packages/scope/src/Resolver/ScopeResolver.php` — update `findStorage()` logic and all callers
  - `packages/scope/tests/Unit/Resolver/ScopeResolverTest.php` — add trait-based entity test cases
- Current flow in `resolved()` / `resolvedAt()`: call `findCompanion($entity)` → returns `?ScopedOverridesEntity`. New flow: call `findStorage($entity)` → returns `?HasScopesInterface`.
- `findStorage()` resolution order (this ordering is part of the contract):
  1. If `$entity instanceof HasScopesInterface` → return `$entity` (the entity IS the storage).
  2. Otherwise iterate `$entity->companions()` and return the first companion that `instanceof HasScopesInterface` (covers `ScopedOverridesEntity` and any other future `HasScopesInterface` implementor).
  3. Otherwise return `null`.
- `setOverride()` currently calls `createCompanion()` when no companion exists. With the trait approach the entity is already the storage — when `findStorage()` returns the entity itself, no companion creation happens; `setOverride()` is called directly on that storage object.
- `clearOverride()` same pattern: when `findStorage()` returns a value (trait-based or companion-based), call `clearOverride()` on it. When `findStorage()` returns null AND the entity does NOT implement `HasScopesInterface`, silently return (existing behavior preserved).
- Rename internal helper from `findCompanion()` to `findStorage()` returning `?HasScopesInterface` to reflect the widened contract. Update the `use` block to import `HasScopesInterface`; the `ScopedOverridesEntity` import remains only in `createCompanion()` (which still hunts specifically for `ScopedOverridesEntity` subclasses in the extender list).
- `createCompanion()` is only called when the entity does NOT implement `HasScopesInterface` and no companion exists. Its logic and error message are unchanged — it intentionally narrows to `ScopedOverridesEntity` subclasses because that is the only known persistence-ready extender pattern. A future `HasScopesInterface` extender that is not a `ScopedOverridesEntity` would not be auto-created (callers must attach it themselves).
- Edge case: if the entity implements `HasScopesInterface` AND has a `ScopedOverridesEntity` companion attached (pathological mixed setup), the entity-self wins per the ordering above. Task 036's validator additionally surfaces this conflict at boot time.

## Requirements (Test Descriptions)
- [ ] `it resolves a scoped value when the entity itself implements HasScopesInterface`
- [ ] `it falls back to the column value when entity implements HasScopesInterface but has no override`
- [ ] `it resolves a scoped value when a ScopedOverridesEntity companion is attached (backward compat)`
- [ ] `it sets an override directly on the entity when it implements HasScopesInterface and no companion is attached or created`
- [ ] `it clears an override directly on the entity when it implements HasScopesInterface`
- [ ] `it silently no-ops when clearOverride is called on a trait-based entity that has no overrides yet`
- [ ] `it throws ScopeContextException when setOverride is called on an entity that does not implement HasScopesInterface and has no companion registered as an extender`
- [ ] `it resolvedAt returns the correct value for an explicit scope on a trait-based entity`
- [ ] `it returns the column value when resolvedAt finds no override for the given scope on a trait-based entity`
- [ ] `it prefers the entity itself over any attached companion when both implement HasScopesInterface (entity-self wins ordering)`
- [ ] `it does not create or attach a companion when setOverride is called on a trait-based entity`

## Acceptance Criteria
- All requirements have passing tests
- No companion class created or attached when entity implements `HasScopesInterface`
- Existing companion-based tests continue passing (backward compatibility)
- `findCompanion()` replaced by `findStorage()` returning `?HasScopesInterface` with documented ordering: entity-self first, then companions, then null
- `createCompanion()` retains its existing signature and `ScopedOverridesEntity`-narrowing behavior — only reachable from the legacy companion path
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
