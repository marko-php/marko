# Task 011: Repository Return Type Migration — findAll/findBy Return EntityCollection

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Migrate the `Repository` and `RepositoryInterface` return types so that `findAll()` and `findBy()` return `EntityCollection` instead of `array`. This is the final integration step that makes EntityCollection the standard return type for all collection queries throughout the framework.

## Context
- Modify: `packages/database/src/Repository/RepositoryInterface.php`
- Modify: `packages/database/src/Repository/Repository.php`
- Test file: `packages/database/tests/Repository/RepositoryReturnTypeTest.php`
- This is a breaking change for anyone extending Repository — EntityCollection implements Countable and IteratorAggregate, so foreach and count() still work
- `toArray()` is available as an escape hatch
- `find()`, `findOrFail()`, `findOneBy()` still return single entities (no change)

## Requirements (Test Descriptions)

### RepositoryInterface Changes
- [ ] `it declares findAll return type as EntityCollection`
- [ ] `it declares findBy return type as EntityCollection`

### Repository Implementation
- [ ] `it returns EntityCollection from findAll`
- [ ] `it returns EntityCollection from findBy`
- [ ] `it returns empty EntityCollection from findAll when no entities exist`
- [ ] `it returns empty EntityCollection from findBy when no matches`
- [ ] `it returns EntityCollection that is iterable with foreach`
- [ ] `it returns EntityCollection that is countable`

### Backward Compatibility
- [ ] `it returns EntityCollection that provides toArray for array access`

## Acceptance Criteria
- All requirements have passing tests
- RepositoryInterface updated with EntityCollection return types
- Repository implementation wraps results in EntityCollection
- Existing repository tests updated to expect EntityCollection (or still pass due to interface compatibility)
- find(), findOrFail(), findOneBy() return types unchanged
- Strict types, @throws tags maintained
- NOTE: This task modifies `Repository.php` and `RepositoryInterface.php` which are also modified by tasks 005 and 007. Be aware of potential merge conflicts if tasks run close together

## Implementation Notes
(Left blank - filled in by programmer during implementation)
