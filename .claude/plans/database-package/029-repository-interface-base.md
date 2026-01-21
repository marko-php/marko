# Task 029: Repository Interface and Base Class

**Status**: completed
**Depends on**: 028
**Retry count**: 0

## Description
Create the RepositoryInterface and base Repository class that provides data access methods for entities. Repositories handle persistence using the query builder and hydrator. Child classes define ENTITY_CLASS constant.

## Context
- Related files: packages/database/src/Repository/RepositoryInterface.php, Repository.php
- Patterns to follow: Repository pattern, ENTITY_CLASS constant in child classes
- Uses QueryBuilder internally, returns hydrated entities

## Requirements (Test Descriptions)
- [x] `it defines RepositoryInterface with find(id) method`
- [x] `it defines RepositoryInterface with findAll() method`
- [x] `it defines RepositoryInterface with findBy(criteria) method`
- [x] `it defines RepositoryInterface with findOneBy(criteria) method`
- [x] `it defines RepositoryInterface with save(entity) method`
- [x] `it defines RepositoryInterface with delete(entity) method`
- [x] `it creates Repository base class implementing interface`
- [x] `it requires ENTITY_CLASS constant in concrete repositories`
- [x] `it throws RepositoryException if ENTITY_CLASS not defined`
- [x] `it uses EntityMetadata to determine table and columns`
- [x] `it uses EntityHydrator to convert rows to entities`
- [x] `it injects ConnectionInterface via constructor`

## Acceptance Criteria
- All requirements have passing tests
- Interface is minimal and focused
- ENTITY_CLASS constant pattern is explicit
- Base class provides default implementations

## Implementation Notes
Created the following files:
- `packages/database/src/Repository/RepositoryInterface.php` - Interface with find, findAll, findBy, findOneBy, save, and delete methods
- `packages/database/src/Repository/Repository.php` - Abstract base class implementing the interface
- `packages/database/src/Exceptions/RepositoryException.php` - Exception class for repository errors
- `packages/database/tests/Repository/RepositoryTest.php` - Comprehensive tests for all requirements

Key design decisions:
- Uses ConnectionInterface directly for SQL queries (raw SQL with parameterized bindings)
- EntityMetadataFactory parses entity class to get table name and column mappings
- EntityHydrator converts database rows to entity objects and tracks dirty state
- ENTITY_CLASS constant must be defined in concrete repository classes
- Repository methods use property-to-column mapping for criteria in findBy/findOneBy
- save() method handles both insert (new entity) and update (existing entity)
- insert() automatically sets generated ID on auto-increment entities via lastInsertId()
