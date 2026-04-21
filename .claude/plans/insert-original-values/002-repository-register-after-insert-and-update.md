# Task 002: Register original values from Repository::insert() and update()

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Call `EntityHydrator::registerOriginalValues()` from `Repository::insert()` after the primary key is assigned, and from `Repository::update()` after a successful UPDATE. This ensures a freshly inserted entity — and an entity that has been updated in-memory — can be mutated and saved again in the same request with the UPDATE correctly persisted.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php`
    - `insert()` at lines 406–438 — add `$this->hydrator->registerOriginalValues($entity, $this->metadata);` as the **last statement of the method**, OUTSIDE the `if ($pkProperty?->isAutoIncrement === true)` block. The call must run for every successful insert, including entities with non-auto-increment PKs or no PK property, not only auto-increment ones. Placing it inside that `if` would reintroduce the bug for those cases.
    - `update()` at lines 445–495 — after `$this->connection->execute($sql, $bindings);` at line 494 and before the method returns, call `$this->hydrator->registerOriginalValues($entity, $this->metadata);` so the entity's post-UPDATE state becomes its new baseline. Do NOT place it before the early `return` at line 457 (no-op path) — when there are no dirty properties, the existing baseline is still correct and re-registration is unnecessary.
  - `packages/database/tests/Repository/RepositoryTest.php` — add regression tests.
- Patterns to follow:
  - Existing repository tests set up an in-memory SQLite connection and a test entity class. Reuse whatever fixtures are already in `RepositoryTest.php`.

## Requirements (Test Descriptions)
- [x] `it persists update when entity is mutated and saved after initial insert in same request`
- [x] `it persists multiple sequential updates on an entity inserted in the same request`
- [x] `it does not execute SQL on save when no properties have changed since last insert`
- [x] `it does not execute SQL on save when no properties have changed since last update`
- [x] `it registers original values after insert for entities with non-auto-increment primary keys` (verify via `hydrator->getDirtyProperties()` returning `[]` immediately post-insert, or via a subsequent mutate+save actually issuing UPDATE)
- [x] Verify data is persisted by re-reading from the database (e.g., `find()` or raw query), not only by inspecting the in-memory entity, since the bug manifests as a silently skipped UPDATE — asserting the entity's own property value would pass even with the bug present.

## Acceptance Criteria
- All requirements have passing tests in `RepositoryTest` (or a dedicated test file if more idiomatic).
- Existing repository test suite still passes.
- Lint clean on `Repository.php` and any touched test files.
- PR title: `fix: save() silently skips update for entities inserted in the same request`.
- PR body includes `Closes #36` and is labeled `bug`.

## Implementation Notes
- Added `$this->hydrator->registerOriginalValues($entity, $this->metadata);` as the last statement of `Repository::insert()`, outside and after the auto-increment `if` block, so it runs for all inserts regardless of PK type.
- Added the same call at the end of `Repository::update()`, after `$this->connection->execute($sql, $bindings)`, so the post-UPDATE state becomes the new dirty-check baseline. The early-return no-op path (no dirty properties) is left alone — when nothing changed, the existing baseline is still correct.
- Added a `createStorageConnection()` helper in `RepositoryTest.php` that maintains an in-memory `$storage` array and properly simulates INSERT (auto-increment id assignment), UPDATE (column-by-column parse of SET clause), and SELECT by id — allowing tests to verify data via `find()` rather than only via the in-memory entity.
- Five regression tests added inside a `describe('register original values after insert and update')` block in `RepositoryTest.php`.
- Lint clean; full suite: 591 passed, 0 failures.
