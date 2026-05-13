# Task 036: Update `ScopedEntityValidator` for Trait-Based Entities

**Status**: completed
**Depends on**: [033]
**Retry count**: 0

## Description
Update `ScopedEntityValidator::validate()` to accept trait-based entities as valid. Currently it throws `ScopeConfigurationException` unless the entity has a registered `ScopedOverridesEntity` companion. With the `HasScopes` trait, an entity can provide its own storage — no companion required.

## Context
- Related files:
  - `packages/scope/src/Validation/ScopedEntityValidator.php` — add early-return when entity `implements HasScopesInterface`; add conflict check when entity uses BOTH the trait and a `ScopedOverridesEntity` extender
  - `packages/scope/src/Exceptions/ScopeConfigurationException.php` — add new factory `traitAndCompanionConflict(string $parentClass, string $extenderClass): self`
  - `packages/scope/tests/Unit/Validation/` — add trait-based entity test cases (including the conflict case)
- Current logic: if entity has scoped properties → look for `ScopedOverridesEntity` extender → throw if missing.
- New logic:
  1. If entity has no scoped properties → return early (unchanged).
  2. If `is_a($entityClass, HasScopesInterface::class, true)` returns true:
     - If entity ALSO has a `ScopedOverridesEntity` subclass in its extenders → throw `ScopeConfigurationException::traitAndCompanionConflict()` with a clear message. This prevents the cryptic `EntityException::duplicateColumnInExtender('scopes', ...)` that would otherwise fire from `SchemaRegistry` at schema-build time.
     - Otherwise → return (valid).
  3. Otherwise fall through to existing companion check (unchanged).
- The validator receives a class-string, not an instance, so use `is_a($entityClass, HasScopesInterface::class, true)` for the interface check. Note: this returns false if the class is not autoloadable; the validator is invoked at boot after autoload registration, so this is safe.
- The existing companion check path and the existing `missingOverridesExtender` / `wrongOverridesExtenderBase` factory methods remain unchanged.

## Requirements (Test Descriptions)
- [ ] `it passes validation when the entity class implements HasScopesInterface and has scoped properties`
- [ ] `it passes validation when the entity has a ScopedOverridesEntity companion (backward compat)`
- [ ] `it passes validation when the entity has no scoped properties at all`
- [ ] `it throws ScopeConfigurationException when entity has scoped properties but neither trait nor companion`
- [ ] `it throws ScopeConfigurationException with wrongOverridesExtenderBase when extender exists but is not ScopedOverridesEntity`
- [ ] `it throws ScopeConfigurationException via traitAndCompanionConflict when entity uses HasScopes trait AND has a ScopedOverridesEntity extender registered`
- [ ] `the traitAndCompanionConflict exception message names both the parent class and the conflicting extender class`

## Acceptance Criteria
- All requirements have passing tests
- Trait-based entity with scoped properties passes without any companion registered
- Trait-based entity with an accidentally-registered `ScopedOverridesEntity` extender fails with a clear scope-domain exception (not the downstream schema-build error)
- New `ScopeConfigurationException::traitAndCompanionConflict()` factory exists with an actionable message and suggestion ("Remove either the `use HasScopes;` trait or the extender class — they both contribute a `scopes` column")
- Existing exception paths and messages unchanged
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
