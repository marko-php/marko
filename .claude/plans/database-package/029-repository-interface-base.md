# Task 029: Repository Interface and Base Class

**Status**: pending
**Depends on**: 028
**Retry count**: 0

## Description
Create the RepositoryInterface and base Repository class that provides data access methods for entities. Repositories handle persistence using the query builder and hydrator. Child classes define ENTITY_CLASS constant.

## Context
- Related files: packages/database/src/Repository/RepositoryInterface.php, Repository.php
- Patterns to follow: Repository pattern, ENTITY_CLASS constant in child classes
- Uses QueryBuilder internally, returns hydrated entities

## Requirements (Test Descriptions)
- [ ] `it defines RepositoryInterface with find(id) method`
- [ ] `it defines RepositoryInterface with findAll() method`
- [ ] `it defines RepositoryInterface with findBy(criteria) method`
- [ ] `it defines RepositoryInterface with findOneBy(criteria) method`
- [ ] `it defines RepositoryInterface with save(entity) method`
- [ ] `it defines RepositoryInterface with delete(entity) method`
- [ ] `it creates Repository base class implementing interface`
- [ ] `it requires ENTITY_CLASS constant in concrete repositories`
- [ ] `it throws RepositoryException if ENTITY_CLASS not defined`
- [ ] `it uses EntityMetadata to determine table and columns`
- [ ] `it uses EntityHydrator to convert rows to entities`
- [ ] `it injects ConnectionInterface via constructor`

## Acceptance Criteria
- All requirements have passing tests
- Interface is minimal and focused
- ENTITY_CLASS constant pattern is explicit
- Base class provides default implementations

## Implementation Notes
(Left blank - filled in by programmer during implementation)
