# Task 037: Update `marko/scope` README for `HasScopes` Trait

**Status**: pending
**Depends on**: [033, 034, 035, 036]
**Retry count**: 0

## Description
Update the `marko/scope` README to document `HasScopes` as the primary (simpler) API for declaring scope storage on an entity. Move the companion-class pattern to a secondary "Alternative: companion class" section so existing users are not broken but new users reach the trait-based path first.

## Context
- Related files:
  - `packages/scope/README.md` — update storage section (this file was produced by task 026 and is currently a short overview that defers detail to external docs; the trait additions warrant inline documentation here)
- The README currently does not document either storage approach in detail (only a quick example with `setOverride`). The new documentation should lead with the trait, then show the companion as an opt-in alternative for cases where separation of concerns is preferred (e.g., separate package providing overrides for another package's entity).
- Required README sections to add/update:
  1. **Quick start** — `use HasScopes` + `implements HasScopesInterface` on the entity
  2. **How it works (schema)** — explain that `#[Column]` on the trait property is picked up by `EntityMetadataFactory` automatically; run migration to add the `scopes` column
  3. **Alternative: companion class** — existing `ScopedOverridesEntity` pattern with rationale ("useful when the scoped overrides are contributed by a separate package, or when you cannot modify the entity class")
  4. **Migration / Don't mix them** — explicit note: do NOT use both `use HasScopes` and a `ScopedOverridesEntity` extender on the same entity. The boot-time validator (task 036) will surface a `ScopeConfigurationException::traitAndCompanionConflict()` if you do; if validation is bypassed the schema build will fail with `EntityException::duplicateColumnInExtender('scopes', ...)`.
  5. **Batch insert note** — trait-based entities have no attached companion, so they are compatible with `Repository::insertBatch()`. Companion-based scoped entities are not (`BatchInsertException::companionsNotSupported` fires).
  6. **Resolver API** — show `resolved()`, `setOverride()`, `clearOverride()` — these work identically regardless of which storage approach is used.
- The `ReadmeTest` in `packages/scope/tests/Unit/ReadmeTest.php` is strict and verifies specific substrings. After edits the README MUST still contain: `# marko/scope`, `Scoped attributes for entities with multi-axis hierarchical fallback`, `## Installation`, `composer require marko/scope`, `scope-mysql`, `scope-pgsql`, `#[Scoped`, `setOverride`, `resolved(`, `locale`, `Product`, `$name`, `## Documentation`.

## Requirements (Test Descriptions)
- [x] `it passes the existing ReadmeTest after updates`
- [x] `it documents HasScopes trait as the primary storage option`
- [x] `it documents the companion-class approach as an alternative`
- [x] `it warns against using HasScopes trait and ScopedOverridesEntity extender on the same entity`
- [x] `it documents that trait-based entities are compatible with Repository::insertBatch while companion-based entities are not`

## Acceptance Criteria
- `ReadmeTest` passes
- README accurately describes both storage approaches and the conflict between them
- README documents the batch-insert compatibility difference
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
