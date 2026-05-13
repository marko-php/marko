# Task 013: `ScopedEntityValidator` (boot-time integrity check)

**Status**: pending
**Depends on**: 008, 010

## Description
With the override companion implemented as a normal `Entity` extender (task 010), hydration and persistence work via the existing `marko/database` machinery — no `EntityHydrator::hydrate` plugin is required.

This task replaces the original "hydrate plugin" with a boot-time validator: for every entity class that declares at least one `#[Scoped]` property, there MUST be a registered `ScopedOverridesEntity` subclass with `#[Table(extends: ThatEntity::class)]`. Otherwise, an entity could declare scoped fields whose overrides would silently go nowhere.

The validator scans entity classes via `ScopeMetadataFactory` (the same factory that drives the resolver) and asks `EntityMetadata::extenders` for the corresponding override extender. The exception is loud and actionable — it names the missing companion class and shows the exact one-line declaration the developer needs to add.

## Context
- Related files: `packages/scope/src/Validation/ScopedEntityValidator.php` (new), `packages/scope/src/Exceptions/ScopeConfigurationException.php` (extend with `missingOverridesExtender` factory)
- Patterns to follow: Boot-time validation pattern used elsewhere in marko-* packages. Loud error with `ScopeConfigurationException` (task 004).
- Integration: invoked by `module.php` `boot` hook OR by a CLI `scope:check` command (decide during implementation; if both, boot hook is authoritative). The test surface is a direct unit test against the validator class.

## Requirements (Test Descriptions)
- [ ] `it passes for an entity with no Scoped properties`
- [ ] `it passes for an entity with Scoped properties when a matching ScopedOverridesEntity extender is registered`
- [ ] `it throws ScopeConfigurationException when an entity has Scoped properties but no extender is linked`
- [ ] `it throws ScopeConfigurationException when the linked extender does not extend ScopedOverridesEntity`
- [ ] `it includes the parent entity FQCN in the exception message`
- [ ] `it lists each Scoped property and its declared axes in the exception context`
- [ ] `it provides a one-line class declaration including the Table extends attribute in the exception suggestion`

## Acceptance Criteria
- `ScopedEntityValidator::validate(string $entityClass): void` accepts an entity FQCN and throws on failure.
- Reads `ScopeMetadataFactory::for($entityClass)` to learn whether the entity has any scoped properties. Returns immediately when `hasScopedProperties()` is false.
- Reads `EntityMetadataFactory::parse($entityClass)->extenders` and asserts at least one entry is a subclass of `ScopedOverridesEntity`.
- Exception thrown as `ScopeConfigurationException::missingOverridesExtender(string $parentClass, array $scopedProperties)` static factory. The message names the parent FQCN; context enumerates `#[Scoped]` properties with their axes; suggestion shows the exact `#[Table(extends: ...)] class {Parent}ScopedOverrides extends ScopedOverridesEntity {}` snippet ready to paste.
- A second variant covers "extender exists but wrong base class" — `ScopeConfigurationException::wrongOverridesExtenderBase(string $parentClass, string $extenderClass)`.

## Implementation Notes
(Left blank — filled in during implementation.)
