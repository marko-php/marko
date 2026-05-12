# Task 003: `Entity::companions()` accessor + `EntityHydrator` companion storage

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a small companion-bag API on the `Entity` base class so a parent entity can carry hydrated extender instances. Storage lives in a new `WeakMap<Entity, array<class-string, Entity>>` on `EntityHydrator` (the same pattern already used for `originalValues`). Public surface on `Entity`:

- `companions(): array` returns `array<class-string, Entity>`.
- `companion(string $class): ?Entity` with `@template T of Entity` and `@param class-string<T> $class` for typed lookup and IDE inference. The `class-string<T>` annotation is required for static-analysis inference; a bare `string` will not infer.
- `attachCompanion(Entity $companion): void` is the public attach API for user code (needed when a freshly-constructed parent must carry companions before the first save). It delegates into the same WeakMap.

Internally, `EntityHydrator` also exposes a package-internal `attachCompanion()` used during read-side hydration (Task 006). Both surfaces write through to the same WeakMap, which lives on the hydrator (not on `Entity`) to keep the base class stateless.

## Context
- Related files:
  - `packages/database/src/Entity/Entity.php` (modify — currently empty body)
  - `packages/database/src/Entity/EntityHydrator.php` (modify — add companions WeakMap + attach/get helpers)
  - `packages/database/tests/Entity/EntityTest.php` (add if missing)
  - `packages/database/tests/Entity/EntityHydratorTest.php` (extend)
- Patterns to follow:
  - Existing `EntityHydrator::$originalValues` WeakMap — mirror that for `companions`
  - No magic methods, explicit accessors

## Requirements (Test Descriptions)
- [x] `it returns empty companions array for a fresh entity`
- [x] `it returns null from companion when class is not attached`
- [x] `it attaches a companion via hydrator and exposes it through companions array`
- [x] `it attaches a companion via Entity::attachCompanion and exposes it through companions array`
- [x] `it returns the same companion instance from companion(class) lookup`
- [x] `it returns the typed companion instance with correct class via companion lookup`
- [x] `it keeps companion bags isolated between two entity instances`
- [x] `it allows multiple companions of different classes on the same entity`
- [x] `it overwrites an existing companion of the same class when reattached`
- [x] `it shares storage between the public Entity::attachCompanion and the internal hydrator attach (one WeakMap, not two)`

## Acceptance Criteria
- All requirements have passing tests
- `Entity::companions()` returns `array<class-string, Entity>`
- `Entity::companion(string $class)` docblock is `@template T of Entity`, `@param class-string<T> $class`, `@return T|null` (the `class-string<T>` form is required for IDE/Phpstan inference)
- `Entity::attachCompanion(Entity $companion): void` exists as the public attach API; it delegates into the hydrator's WeakMap (the hydrator is injected or accessed via a static accessor — design choice: prefer a small `EntityCompanionStorage` value object held by both `Entity` static state and `EntityHydrator`, OR inject the hydrator into Entity. Confirm during build; whatever the wiring, both APIs read/write the SAME map).
- Companion storage is in a single shared `WeakMap` — no instance properties on `Entity`, no duplicate maps
- Two `Entity` instances do not share companion state
- Code follows code standards
