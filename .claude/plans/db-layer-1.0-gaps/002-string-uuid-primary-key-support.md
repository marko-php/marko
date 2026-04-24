# Task 002: String/UUID Primary Key Support

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Widen primary key support from `int` to `int|string` so UUID-keyed projects can adopt Marko. Touches `Repository::find()`, all code paths that pass a PK around (`save()` update path, `delete()`), and `RelationshipLoader` which currently assumes integer foreign keys. Entity hydration and dirty tracking must round-trip string PKs correctly.

## Context
- Related files:
  - `packages/database/src/Repository/RepositoryInterface.php` — widen `find()` AND `findOrFail()` signatures (both currently `int $id`)
  - `packages/database/src/Repository/Repository.php` — update `find()`, `findOrFail()`, `exists(int $id)`, `isColumnUnique(..., ?int $excludeId)`, and any `int`-typed PK parameter on the update path
  - `packages/database/src/Entity/RelationshipLoader.php` — batched WHERE IN currently assumes ints
  - `packages/database/src/Entity/EntityHydrator.php` — ensure string PK columns hydrate correctly and auto-increment-skip logic still works for non-autoincrement string PKs
  - `packages/database/src/Entity/EntityMetadata.php` — PK type info
  - `packages/database/src/Exceptions/RepositoryException.php` — `entityNotFound(..., $id)` accepts mixed/scalar for exception rendering
  - Any other spot typed `int $id` for a PK parameter (grep `int \$id` across `packages/database*/src`)
- Patterns to follow: existing type-casting logic in `EntityHydrator` for enum/DateTimeImmutable.
- Note: for string PKs the client supplies the value before insert (UUID generated app-side); `Repository::insert()` currently only skips the PK column when `isAutoIncrement === true`, so non-autoincrement string PKs flow through naturally — verify with a test.

## Requirements (Test Descriptions)
- [x] `it finds an entity by string primary key`
- [x] `it saves a new entity with a string primary key`
- [x] `it updates an existing entity with a string primary key via dirty tracking`
- [x] `it deletes an entity with a string primary key`
- [x] `it loads a BelongsTo relationship when the foreign key is a string`
- [x] `it loads a HasMany relationship when the parent primary key is a string`
- [x] `it batches WHERE IN queries correctly for string foreign keys without SQL injection`
- [x] `it still supports integer primary keys with identical behavior`
- [x] `it rejects non int/string primary key values with a descriptive exception`

## Acceptance Criteria
- `RepositoryInterface::find()` AND `findOrFail()` accept `int|string`.
- `Repository::exists()` and `isColumnUnique($excludeId)` widened to `int|string`.
- All `int` parameter types for primary keys widened consistently across database packages.
- `RelationshipLoader` handles string FKs with parameterized queries (no injection).
- Integration tests on both MySQL and PostgreSQL cover string PK round-trips.
- Upgrade note drafted for the commit message describing the signature widening.
- `RepositoryException::entityNotFound()` signature updated to accept `int|string` (or `mixed`) for the id parameter so string PK failures render correctly.

## Implementation Notes

### Files Modified
- `packages/database/src/Repository/RepositoryInterface.php` — widened `find()` and `findOrFail()` from `int $id` to `int|string $id`
- `packages/database/src/Repository/Repository.php` — widened `find()`, `findOrFail()`, `exists()`, and `isColumnUnique($excludeId)` to `int|string`
- `packages/database/src/Exceptions/RepositoryException.php` — widened `entityNotFound()` second param to `int|string $id`
- `packages/database/src/Entity/EntityHydrator.php` — updated `isNew()`: for auto-increment PKs keeps null-check; for non-auto-increment PKs (client-supplied UUIDs), uses `originalValues` WeakMap to distinguish new vs. persisted entities
- `packages/database/tests/Repository/StringPrimaryKeyTest.php` — new test file with all 9 requirements
- `packages/database/tests/Repository/RepositoryTest.php` — updated `find`/`findOrFail` type assertion tests to use `str_contains` instead of exact string match (PHP reflection returns `string|int` in alphabetical order)
- `packages/admin-auth/tests/Unit/AdminUserProviderTest.php` — updated anonymous class stubs: `find`/`findOrFail` widened to `int|string`, added `insertBatch()` stub

### Key Design Decision
`EntityHydrator::isNew()` was extended with a two-path approach:
1. **Auto-increment PKs**: original behavior — null PK means new
2. **Non-auto-increment PKs** (e.g. UUID): `originalValues` WeakMap check — entity is new if it has no tracked snapshot, meaning it was never hydrated from the DB or had `registerOriginalValues()` called

`RelationshipLoader` required no changes — `whereIn()` already passes values as parameterized bindings (not interpolated), so string FKs work safely out of the box.

### Breaking Change
`RepositoryInterface::find()` and `findOrFail()` parameter widened from `int` to `int|string`. Any code that explicitly implements `RepositoryInterface` with `int $id` must be updated to `int|string $id`. Concrete classes extending `Repository` are automatically compatible.
