# Task 030: Repository Query Methods

**Status**: completed
**Depends on**: 029
**Retry count**: 0

## Description
Implement the query methods in the Repository base class: find, findBy, save, delete. These methods use the query builder and hydrator to provide a complete data access layer.

## Context
- Related files: packages/database/src/Repository/Repository.php
- Patterns to follow: Uses QueryBuilder for SQL, Hydrator for conversion
- save() handles both INSERT (new) and UPDATE (existing)

## Requirements (Test Descriptions)
- [x] `it finds entity by primary key with find(id)`
- [x] `it returns null when entity not found`
- [x] `it finds all entities with findAll()`
- [x] `it finds entities by criteria array with findBy(array)`
- [x] `it finds single entity by criteria with findOneBy(array)`
- [x] `it inserts new entity with save() when no ID`
- [x] `it updates existing entity with save() when has ID`
- [x] `it only updates dirty fields on existing entity`
- [x] `it sets auto-generated ID on entity after insert`
- [x] `it deletes entity with delete()`
- [x] `it provides query() method returning QueryBuilder for custom queries`
- [x] `it hydrates results from query() automatically`
- [x] `it supports count() method returning total count`
- [x] `it supports exists(id) method returning boolean`

## Acceptance Criteria
- All requirements have passing tests
- save() detects new vs existing via primary key
- Custom queries return hydrated entities
- Dirty checking enables efficient updates

## Implementation Notes
- Fixed PHP 8.5 syntax error with `instanceof static::ENTITY_CLASS` by using a variable
- Modified `update()` method to only update dirty fields using `EntityHydrator::getDirtyProperties()`
- Added `query()` method that returns a `RepositoryQueryBuilder` wrapping the `QueryBuilderInterface`
- Created new `RepositoryQueryBuilder` class that implements `QueryBuilderInterface` and adds `getEntities()` and `firstEntity()` methods for hydration
- Added `count()` method using COUNT(*) SQL aggregate
- Added `exists(int $id)` method that delegates to `find()`
- Constructor now accepts optional `$queryBuilderFactory` closure parameter for creating query builders
- Added `RepositoryException::queryBuilderNotConfigured()` and `RepositoryException::invalidQueryBuilder()` exception methods
