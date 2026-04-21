# Task 001: Add EntityHydrator::registerOriginalValues()

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a public method to `EntityHydrator` that snapshots an entity's current property values into the `$originalValues` WeakMap, so dirty-checking works on entities that never passed through `hydrate()` (e.g., freshly inserted entities).

## Context
- Related files:
  - `packages/database/src/Entity/EntityHydrator.php` — add new method; mirror the loop at lines 48–64 from `hydrate()`, minus the DB-to-PHP conversion (values are already PHP-typed).
  - `packages/database/tests/Entity/EntityHydratorTest.php` — add unit tests.
- Patterns to follow:
  - Use the same `isInitialized()` guard used by `getDirtyProperties()` (EntityHydrator.php:157) to skip uninitialized properties.
  - Assignment form: `$this->originalValues[$entity] = $values;` (matches line 64).

## Requirements (Test Descriptions)
- [x] `it registers original values for an entity with initialized properties`
- [x] `it makes getDirtyProperties return empty array immediately after registration`
- [x] `it detects a property as dirty after mutation post-registration`
- [x] `it skips uninitialized properties when registering`
- [x] `it replaces prior original values when called a second time` (mutate entity, call `registerOriginalValues` again, assert `getDirtyProperties` is `[]` and that the new property value — not the original — is now the baseline)
- [x] `it snapshots values by value, not by reference` (for scalar/primitive properties, mutating the entity property after registration must not retroactively alter the stored original value)

## Signature
```php
public function registerOriginalValues(
    Entity $entity,
    EntityMetadata $metadata,
): void
```
No return value. Idempotent on re-call (overwrites prior snapshot).

## Acceptance Criteria
- All requirements have passing tests in `EntityHydratorTest`.
- New method has full type declarations and docblock consistent with `hydrate()` and `getOriginalValues()`.
- `declare(strict_types=1)` preserved; no final classes introduced.
- Lint clean on `EntityHydrator.php` and `EntityHydratorTest.php`.

## Implementation Notes
Added `registerOriginalValues(Entity $entity, EntityMetadata $metadata): void` to `EntityHydrator`. The method iterates `$metadata->properties`, skips uninitialized properties via `ReflectionProperty::isInitialized()`, reads each value directly (no DB-to-PHP conversion needed), and writes the snapshot to `$this->originalValues[$entity]`. Scalars are stored by value so subsequent property mutations do not retroactively alter the snapshot. Overwrites any prior snapshot on re-call, making it idempotent. Six new tests added to `EntityHydratorTest.php` covering all requirements. Full test suite passes (4681 tests). Both files lint-clean.
