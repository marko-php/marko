# Task 003: Create PostRepository

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the PostRepository class that extends the abstract Repository base class. This provides data access methods for Post entities while remaining database-agnostic.

## Context
- Related files:
  - `packages/blog/src/Repositories/PostRepository.php` (to create)
  - `packages/database/src/Repository/Repository.php` (base class)
  - `packages/database/src/Repository/RepositoryInterface.php` (interface)
  - `packages/database/tests/Repository/RepositoryTest.php` (example implementation)
- Patterns to follow: UserRepository example in database package tests

## Requirements (Test Descriptions)
- [ ] `it extends the Repository base class`
- [ ] `it defines ENTITY_CLASS constant pointing to Post entity`
- [ ] `it can find a post by id using inherited find method`
- [ ] `it can find all posts using inherited findAll method`
- [ ] `it can find posts by criteria using inherited findBy method`
- [ ] `it provides findBySlug convenience method for slug lookups`

## Acceptance Criteria
- All requirements have passing tests
- Repository uses mock ConnectionInterface in tests
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
