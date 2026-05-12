# Task 007: `Repository` INSERT/UPDATE extender column merge

**Status**: completed
**Depends on**: 004, 006
**Retry count**: 0

## Description
Extend `Repository::insert()` and `Repository::update()` to include columns from any companions attached to the entity, so a single SQL statement persists parent + extension data. `insert` calls the new `hydrator->extractAll($entity, $this->metadata)` (added in Task 006) to merge parent + companion columns into the column list. `update` extends the dirty-property scan to include each companion's dirty properties (the hydrator already tracks `originalValues` per-entity; companions get their own `originalValues` snapshot when they pass through `hydrate()` or when `registerOriginalValues()` is called on them after insert).

INSERT path for never-hydrated companions: when the user attaches a freshly-constructed companion via `Entity::attachCompanion()` and saves, the companion has no `originalValues` yet. `insert()` must call `hydrator->registerOriginalValues($companion, $companionMetadata)` on each attached companion after the INSERT completes so subsequent updates dirty-track correctly.

UPDATE path rolling-deploy: if a companion was never hydrated (because its columns are absent from the DB), it has no `originalValues` and contributes no dirty fields — the existing skip behavior covers this naturally.

Two new safety guards:
1. `Repository::__construct()` MUST raise a loud error (new `RepositoryException::extenderCannotHaveRepository(class-string)`) when `static::ENTITY_CLASS` resolves to an extender (`$this->metadata->isExtender()` is true). Extenders have no PK of their own and are not valid Repository targets.
2. `Repository::insertBatch()` MUST raise a loud error (new `BatchInsertException::companionsNotSupported(class-string)`) if any entity in the batch has companions attached. Batch insert with mixed companion attachment is out of scope for v1.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php` (modify — `insert` ~553–587, `update` ~594–644, `insertBatch` ~261–365, and `__construct` ~68–78)
  - `packages/database/src/Exceptions/RepositoryException.php` (add `extenderCannotHaveRepository`)
  - `packages/database/src/Exceptions/BatchInsertException.php` (add `companionsNotSupported`)
  - `packages/database/tests/Repository/RepositoryTest.php` (extend)
- Patterns to follow:
  - New `hydrator->extractAll($entity, $metadata)` (from Task 006) — produces parent+companion column map in one call
  - Existing `getDirtyProperties()` flow — call it per companion using each companion's own metadata (obtained via the metadata factory)

## Requirements (Test Descriptions)
- [x] `it inserts a parent entity without companions using only parent columns`
- [x] `it inserts a parent entity with one attached companion using merged columns in a single INSERT`
- [x] `it inserts a parent entity with multiple attached companions using all merged columns`
- [x] `it sets the auto-increment id on the parent after insert when companions are attached`
- [x] `it registers originalValues on each attached companion after insert`
- [x] `it updates a parent entity with no companion changes using only parent dirty columns`
- [x] `it updates a parent entity and a dirty companion field in a single UPDATE`
- [x] `it skips a companion update when that companion has no dirty properties`
- [x] `it skips a companion update entirely when the companion was never hydrated (rolling deploy)`
- [x] `it preserves WHERE clause using parent primary key when companions are present`
- [x] `it updates originalValues snapshot on each companion after update`
- [x] `it inserts a parent with a freshly-attached (never-hydrated) companion and registers original values on the companion after INSERT`
- [x] `it throws RepositoryException when constructing a Repository whose ENTITY_CLASS is an extender`
- [x] `it throws BatchInsertException when insertBatch is called with entities that have companions attached`

## Acceptance Criteria
- All requirements have passing tests
- `INSERT` produces a single SQL statement with parent + companion columns
- `UPDATE` produces a single SQL statement with parent dirty + companion dirty columns
- `Repository::__construct()` guards against extender ENTITY_CLASS with a loud, specific error message
- `Repository::insertBatch()` guards against attached companions with a loud, specific error message
- After insert, each attached companion has its `originalValues` snapshot registered (so subsequent `update()` correctly diffs)
- No existing repository tests regress
- The repository continues to work identically for entities with no extenders
- Code follows code standards
