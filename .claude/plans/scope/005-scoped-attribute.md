# Task 005: `#[Scoped]` attribute

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Define the `#[Scoped]` PHP attribute that marks an entity property as scope-aware. Accepts an ordered array of axis names — order is the declared-priority for multi-axis resolution.

## Context
- Related files: `packages/scope/src/Attributes/Scoped.php` (new)
- Patterns to follow: `packages/database/src/Attributes/Column.php`, `.claude/architecture.md` § PHP Attributes.

## Requirements (Test Descriptions)
- [ ] `it is a readonly class targeting properties only`
- [ ] `it accepts an axes array in the constructor`
- [ ] `it defaults axes to empty array meaning single-axis fallback to registry default`
- [ ] `it preserves axes order as declared`
- [ ] `it is reflectable on a property and round-trips via getAttributes`

## Acceptance Criteria
- `Attribute::TARGET_PROPERTY` only — applying to methods/classes is a PHP-level error.
- `axes` property is `public readonly array` (or `public private(set)`).
- Validation of axis names against the registry happens in `ScopeMetadataFactory` (task 008), not in the attribute itself.

## Implementation Notes
(Left blank — filled in during implementation.)
