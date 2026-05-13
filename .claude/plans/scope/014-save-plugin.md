# Task 014: Save-path integration test for `ScopedOverridesEntity`

**Status**: complete
**Depends on**: 010, 012, 013

## Description
With overrides stored on a normal `Entity` extender (task 010), `Repository::save` already persists them — `Repository::insert` calls `EntityHydrator::extractAll` which iterates companions, and `Repository::update` dirty-tracks companion fields and merges them into the same UPDATE. No save plugin is required.

This task adds end-to-end feature tests that confirm the extender path works for scoped overrides:
- Insert a fresh entity with no overrides → JSON column written as NULL.
- Insert with overrides → JSON column written with the serialized map.
- Update an existing entity, mutating only the override → only the `scopes` column is updated, parent columns untouched.
- Clearing all overrides on an entity that previously had some → `scopes` reverts to NULL.

The original "Repository::save plugin" approach is dropped because the `marko/core` plugin system does not walk parent class hierarchies (`Repository` is `abstract`, user repositories are concrete subclasses, and `PluginRegistry::getEffectiveTargetClass` only inspects interfaces — confirmed in `packages/core/src/Plugin/PluginRegistry.php`).

## Context
- Related files: `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php` (new)
- Patterns to follow: Existing repository feature tests in `packages/database/tests/Feature/`. Anonymous-class repositories or fixture entity classes that exercise insert + update through a real (sqlite-in-memory or pgsql/mysql Docker) connection.
- Cross-driver: feature tests parameterized over both drivers if practical; otherwise per-driver smoke tests in the driver packages with the heavy logic exercised against sqlite in the base package.

## Requirements (Test Descriptions)
- [x] `it saves an entity with no overrides leaving the scopes column null` (feature)
- [x] `it saves an entity with overrides serializing them into the scopes column` (feature)
- [x] `it updates only the scopes column when only overrides change` (feature)
- [x] `it writes null into the scopes column when all overrides are cleared` (feature)
- [x] `it round-trips overrides via save then re-hydrate via find` (feature)
- [x] `it dirty-tracks the override companion via Repository::update without a custom plugin` (feature)

## Acceptance Criteria
- Tests live in `packages/scope/tests/Feature/`.
- No `#[Plugin]` classes are introduced for save — the companion is just an extender entity.
- Tests cover both insert and update code paths in `Repository`.

## Implementation Notes
Tests implemented in `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php`.

Pattern used: mock `ConnectionInterface` that logs SQL/bindings to a reference array. All 6 tests passed immediately because `Repository::insert` (via `extractAll`) and `Repository::update` (via companion dirty-tracking) already handled the companion extender path without any plugin.

The round-trip test uses a more sophisticated mock connection that captures the serialized scopes from INSERT bindings and returns them on SELECT, enabling `hydrate()` to reconstruct the companion with its original values.

No `#[Plugin]` classes were introduced — the existing companion extender path handles everything.
