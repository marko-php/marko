# Task 006: `EntityHydrator` companion hydration with rolling-deploy skip

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

## Description
Extend `EntityHydrator::hydrate()` so that when the parent `EntityMetadata` has linked extenders, each extender is hydrated from the same row and attached to the parent entity as a companion. Rolling-deploy safety: if any column for an extender is absent from the row, silently skip that extender's hydration (the column hasn't been migrated yet). Extender hydration reuses the same per-property `convertToPhpType` logic — no new conversion code.

Also add a new `extractAll(Entity $parent, EntityMetadata $parentMetadata): array` method (do NOT change the existing `extract()` signature — keep it single-purpose) that returns merged parent columns + columns from each attached companion. Internally it calls `extract()` per companion using the companion's own metadata. Used by Repository in Task 007.

Decide via metadata, not by sniffing: hydrator looks at `$metadata->extenders` to know which classes to instantiate. It needs per-class metadata for each extender — inject `EntityMetadataFactory` into `EntityHydrator` constructor. Make the new parameter OPTIONAL with a default `null` so existing call sites (`new EntityHydrator()`) and existing tests continue to work without modification. When the factory is null AND `$metadata->extenders` is non-empty, throw a clear error directing the developer to wire the factory; when extenders is empty, the factory is never touched.

A package-internal `attachCompanion(Entity $parent, Entity $companion): void` is added to the hydrator for use during hydration (the public Entity-level attach API was added in Task 003 and writes into the same WeakMap).

## Context
- Related files:
  - `packages/database/src/Entity/EntityHydrator.php` (modify — constructor gets `EntityMetadataFactory`)
  - `packages/database/tests/Entity/EntityHydratorTest.php` (extend)
- Patterns to follow:
  - Existing `hydrate()` reflection + `convertToPhpType` flow — reuse per-extender
  - `WeakMap`-backed companions from Task 003

## Requirements (Test Descriptions)
- [x] `it hydrates only the parent when metadata has no extenders`
- [x] `it hydrates the parent and one companion when metadata has one extender`
- [x] `it hydrates the parent and multiple companions when metadata has multiple extenders`
- [x] `it attaches each companion under its own class-string in the companions bag`
- [x] `it sets companion property values from the same row data`
- [x] `it silently skips an extender whose columns are entirely missing from the row`
- [x] `it partially hydrates an extender when some columns are present and some are missing`
- [x] `it does not set originalValues for an extender that was skipped`
- [x] `it extractAll returns only parent columns when no companions are attached`
- [x] `it extractAll includes companion columns when companions are attached`
- [x] `it extractAll uses each companion's own metadata for column resolution`
- [x] `it does not require the EntityMetadataFactory call for entities without extenders (no extra parse)`
- [x] `it constructs without the EntityMetadataFactory and hydrates non-extended entities correctly (backward compat)`
- [x] `it correctly hydrates companions when the factory has not seen the extender classes before (on-demand parse)`

## Acceptance Criteria
- All requirements have passing tests
- `EntityHydrator` constructor adds an OPTIONAL `?EntityMetadataFactory $metadataFactory = null` parameter — existing call sites (`new EntityHydrator()`) keep compiling
- Existing `extract()` signature is unchanged; new behavior lives in a new `extractAll()` method
- "Silently skip" means no exception, no companion attached, no log noise
- Existing hydrator tests still pass without modification (the new behavior is gated on `$metadata->extenders` being non-empty)
- Code follows code standards
