# Task 008: `ScopeMetadata` + `ScopeMetadataFactory`

**Status**: pending
**Depends on**: 005, 006
**Retry count**: 0

## Description
Reflection-based metadata layer that introspects an entity class for `#[Scoped]` properties, validates declared axes against the registry, and caches the resulting metadata per class.

## Context
- Related files: `packages/scope/src/Metadata/ScopeMetadata.php`, `packages/scope/src/Metadata/ScopeMetadataFactory.php` (new)
- Patterns to follow: `packages/database/src/Entity/EntityMetadataFactory.php` for reflection + caching style. Independent of `EntityMetadata` — does not modify `marko/database`.

## Requirements (Test Descriptions)
- [ ] `it returns empty metadata for entity classes with no Scoped properties`
- [ ] `it discovers Scoped properties on an entity class via reflection`
- [ ] `it returns declared axes for a scoped property in declaration order`
- [ ] `it caches metadata per class within the factory`
- [ ] `it throws UnknownAxisException when a Scoped property declares an unknown axis`
- [ ] `it reports whether a property is scoped via isScoped`
- [ ] `it lists all scoped property names via scopedProperties`

## Acceptance Criteria
- `ScopeMetadata` is `readonly class`.
- Factory caches by FQCN; the same factory instance returns the same metadata object for repeated calls.
- Validation of axes against the registry happens once per class at factory time, not per call.
- `ScopeMetadata::hasScopedProperties(): bool` is exposed so callers (resolver, validator, factory) can cheaply short-circuit for non-scoped classes.

## Implementation Notes
(Left blank — filled in during implementation.)
