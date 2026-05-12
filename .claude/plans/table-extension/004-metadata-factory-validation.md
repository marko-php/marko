# Task 004: `EntityMetadataFactory` validation & extender linking

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Teach `EntityMetadataFactory::parse()` about the new `extends:` param. When an entity has `extends:` set, validate the extender constraints loudly, resolve the table name from the parent's metadata, and produce `EntityMetadata` with `$extends` populated.

Also add two helper methods on the factory:
- `linkExtenders(class-string $parentClass, array<class-string> $extenders): EntityMetadata` — calls `withExtenders()` on the parent's cached metadata, REPLACES the cached instance in the factory's internal `$cache`, and returns the new instance. This is critical: `Repository::__construct()` calls `parse()` and must receive the linked metadata. Without cache replacement, the parent's metadata seen by Repository will have empty `$extenders` and companion hydration/INSERT/UPDATE will never trigger.
- The chained-extension validation must use a dedicated error factory method whose message says: "Chained extension is not supported. {ExtenderClass}'s parent {ParentClass} is itself an extender. Extend the root entity directly." (Not a generic "invalid extends".)

## Context
- Related files:
  - `packages/database/src/Entity/EntityMetadataFactory.php` (modify — extend `extractTableName`, `validateEntity`, parse method)
  - `packages/database/src/Exceptions/EntityException.php` (add new factory error methods)
  - `packages/database/tests/Entity/EntityMetadataFactoryTest.php` (extend or create)
- Patterns to follow:
  - Existing loud error factory methods in `EntityException` — match format (message/context/suggestion)
  - Existing two-step `validateEntity()` + `extractTableName()` flow

## Requirements (Test Descriptions)
- [x] `it parses an extender entity and resolves table name from the parent`
- [x] `it populates extends field on extender metadata with the parent class-string`
- [x] `it throws EntityException when extender declares its own name on Table attribute`
- [x] `it throws EntityException when entity declares neither name nor extends on Table attribute`
- [x] `it throws EntityException when extender declares a primaryKey column`
- [x] `it throws EntityException when extender declares an autoIncrement column`
- [x] `it throws EntityException when extender's parent class does not exist`
- [x] `it throws EntityException when extender's parent class does not extend Entity`
- [x] `it throws EntityException when extender's parent is itself an extender (no chained extension)`
- [x] `it allows extender to declare its own indexes`
- [x] `it allows extender to declare relationships`
- [x] `it caches extender metadata like normal entities`
- [x] `it linkExtenders replaces the cached parent metadata with one that has extenders populated`
- [x] `it linkExtenders returns metadata where isExtended is true`
- [x] `it produces a chained-extension error message that names both the extender and its extender-parent and tells the user to extend the root`

## Acceptance Criteria
- All requirements have passing tests
- New exceptions live in `EntityException` with message/context/suggestion (match existing style)
- `extends:` and `name:` are mutually exclusive — exactly one is required
- Extender metadata has no `primaryKey` of its own (parent owns the PK)
- The factory does NOT validate column-name conflicts between extenders — that requires cross-entity visibility and lives in `SchemaRegistry` (Task 005)
- `EntityMetadataFactory::linkExtenders()` mutates the internal cache: a subsequent `parse(parentClass)` returns the linked metadata
- Chained-extension error message is specific and actionable (names both classes, points at the root)
- Code follows code standards
