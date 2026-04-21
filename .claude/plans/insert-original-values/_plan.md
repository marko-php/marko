# Plan: Fix save() silently skipping UPDATE after INSERT

## Created
2026-04-21

## Status
completed

## Objective
Fix `Repository::save()` silently skipping UPDATE for entities inserted and then mutated in the same request. Register original values on the hydrator after INSERT and after UPDATE so dirty-detection works across the entity's full lifecycle.

## Related Issues
Closes #36

## Scope

### In Scope
- New `EntityHydrator::registerOriginalValues(Entity, EntityMetadata): void` method that snapshots current property values into the WeakMap.
- Call `registerOriginalValues()` from `Repository::insert()` after PK assignment.
- Call `registerOriginalValues()` from `Repository::update()` after a successful UPDATE so subsequent saves correctly no-op or detect only newer changes.
- Unit test for `registerOriginalValues()` in `EntityHydratorTest`.
- Regression tests in `RepositoryTest` covering: insert → mutate → save; insert → mutate → save → mutate → save.
- Lint fixes on all touched files.

### Out of Scope
- Refactoring dirty-tracking to not use WeakMap.
- Any other `save()` bugs unrelated to `$originalValues` population.
- Changing the public Repository/Hydrator API beyond the one new method.

## Success Criteria
- [ ] `EntityHydrator::registerOriginalValues()` exists and snapshots initialized properties.
- [ ] `Repository::insert()` registers original values after PK assignment.
- [ ] `Repository::update()` re-registers original values after a successful UPDATE.
- [ ] Regression test: insert → mutate → save persists the UPDATE.
- [ ] Regression test: insert → mutate → save → mutate → save persists both updates.
- [ ] All existing tests still pass (`pest --parallel`).
- [ ] Lint clean on touched files (`phpcs` and `php-cs-fixer`).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `EntityHydrator::registerOriginalValues()` + unit test | - | completed |
| 002 | Call `registerOriginalValues()` from `Repository::insert()` and `Repository::update()` + regression tests | 001 | completed |

## Architecture Notes
- `EntityHydrator::$originalValues` is a `WeakMap<Entity, array<string,mixed>>` — the new method writes the same shape that `hydrate()` already writes at `EntityHydrator.php:46-64`.
- No value conversion needed in `registerOriginalValues()` — entity property values are already PHP-typed (whereas `hydrate()` converts from DB row values via `convertToPhpType()`).
- Skip uninitialized properties (parallel to the `isInitialized` guard in `getDirtyProperties()` at `EntityHydrator.php:157`).
- In `Repository::insert()` the registration call MUST run outside the `isAutoIncrement` conditional (entities with manually-set PKs must still get a baseline snapshot). In `Repository::update()` the registration call MUST run after `connection->execute()` on the dirty path, not before the `return` on the no-op path.
- Aligns with Marko's "loud errors / no silent failures" core principle.

## Risks & Mitigations
- **Risk:** registering after UPDATE might mask a legitimate follow-up dirty state if reflection values differ from what was written. **Mitigation:** snapshot the entity's *current* PHP values (post-write state), matching the row in DB. If later code re-mutates a property, `getDirtyProperties()` will correctly flag it vs. the new snapshot.
- **Risk:** WeakMap key collision with existing `hydrate()`-populated entries. **Mitigation:** assignment `$this->originalValues[$entity] = $values` replaces cleanly; no collision.
- **Risk:** behavior change breaks existing tests that rely on the no-op UPDATE after INSERT. **Mitigation:** run full suite; any failing test was depending on a bug.
