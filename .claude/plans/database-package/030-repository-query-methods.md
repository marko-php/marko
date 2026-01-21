# Task 030: Repository Query Methods

**Status**: pending
**Depends on**: 029
**Retry count**: 0

## Description
Implement the query methods in the Repository base class: find, findBy, save, delete. These methods use the query builder and hydrator to provide a complete data access layer.

## Context
- Related files: packages/database/src/Repository/Repository.php
- Patterns to follow: Uses QueryBuilder for SQL, Hydrator for conversion
- save() handles both INSERT (new) and UPDATE (existing)

## Requirements (Test Descriptions)
- [ ] `it finds entity by primary key with find(id)`
- [ ] `it returns null when entity not found`
- [ ] `it finds all entities with findAll()`
- [ ] `it finds entities by criteria array with findBy(array)`
- [ ] `it finds single entity by criteria with findOneBy(array)`
- [ ] `it inserts new entity with save() when no ID`
- [ ] `it updates existing entity with save() when has ID`
- [ ] `it only updates dirty fields on existing entity`
- [ ] `it sets auto-generated ID on entity after insert`
- [ ] `it deletes entity with delete()`
- [ ] `it provides query() method returning QueryBuilder for custom queries`
- [ ] `it hydrates results from query() automatically`
- [ ] `it supports count() method returning total count`
- [ ] `it supports exists(id) method returning boolean`

## Acceptance Criteria
- All requirements have passing tests
- save() detects new vs existing via primary key
- Custom queries return hydrated entities
- Dirty checking enables efficient updates

## Implementation Notes
(Left blank - filled in by programmer during implementation)
