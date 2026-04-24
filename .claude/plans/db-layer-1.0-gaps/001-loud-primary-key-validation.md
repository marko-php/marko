# Task 001: Loud Primary Key Validation

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Remove the silent `'id'` column fallback in `Repository` and enforce explicit primary key declaration at entity metadata parse time. Any entity without an explicit PK must throw a descriptive exception when its metadata is built, not later when a query silently does the wrong thing. Aligns with the "loud errors" principle.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php` — silent `'id'` fallbacks at lines 114 (`find()`), 265 (`delete()`), 451 (`update()` path); also the hardcoded `'AND id != ?'` inside `isColumnUnique()` (~line 394)
  - `packages/database/src/Entity/EntityMetadataFactory.php` (where metadata is parsed)
  - `packages/database/src/Entity/EntityMetadata.php` (exposes primary key info)
  - `packages/database/src/Exceptions/` (add a new exception class here)
- Patterns to follow: other loud-error exceptions in `packages/database/src/Exceptions/`.
- The correct attribute flag is `#[Column(primaryKey: true)]` (see `packages/database/src/Attributes/Column.php`); exception message must use that name, NOT `primary:`.

## Requirements (Test Descriptions)
- [x] `it throws MissingPrimaryKeyException at metadata parse time when entity has no primary key attribute`
- [x] `it includes the entity class name in the exception message`
- [x] `it includes a suggestion to add #[Column(primaryKey: true)] in the exception message`
- [x] `it no longer falls back to the literal 'id' column name in Repository::find`
- [x] `it no longer falls back to the literal 'id' column name in Repository::save update path`
- [x] `it no longer falls back to the literal 'id' column name in Repository::delete`
- [x] `it no longer hardcodes 'id' in Repository::isColumnUnique exclude clause — uses the real PK column`
- [x] `it continues to work for entities that DO declare a primary key explicitly`

## Acceptance Criteria
- All requirements have passing tests via `composer test`.
- `MissingPrimaryKeyException` exists and extends the base database exception class.
- No occurrence of the literal string `'id'` as a fallback column name in `Repository` (including `isColumnUnique`).
- Pre-existing lint errors in touched files fixed.
- Any existing test fixtures that implicitly relied on the `'id'` fallback are updated to declare PKs explicitly.
- Audit and update all existing fixture entities in the `packages/database*/tests/` trees — any fixture without an explicit primary key will now fail to build metadata; either add `primaryKey: true` or delete the fixture.

## Implementation Notes
- Created `packages/database/src/Exceptions/MissingPrimaryKeyException.php` extending `MarkoException` with a `noPrimaryKey(string $entityClass)` static factory.
- `EntityMetadataFactory::parse()`: changed `$primaryKey` init from `'id'` to `null`; added validation after column parsing to throw `MissingPrimaryKeyException` when no `primaryKey: true` column is found.
- `EntityMetadata::primaryKey` parameter: removed default value `'id'` — it is now required (guaranteed non-null by factory validation).
- `Repository::find()`, `delete()`, `update()`: removed all `?? 'id'` fallbacks; call `getPrimaryKeyProperty()->columnName` directly.
- `Repository::isColumnUnique()`: replaced hardcoded `' AND id != ?'` with `" AND $pkColumn != ?"` using the real PK column name.
- `packages/admin-auth/src/Entity/RolePermission.php`: added `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;` (pivot table entity was the only production entity missing a PK).
- Test fixtures in `EntityMetadataFactoryTest` and `EntityMetadataTest` updated to include `primaryKey` declarations and pass `primaryKey:` to `EntityMetadata` constructor.
- All 4743 tests pass; lint fixed on all touched files.
