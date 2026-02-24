# Task 019: Database Search Driver

**Status**: pending
**Depends on**: 018
**Retry count**: 0

## Description
Implement the database search driver that uses SQL LIKE queries and filter application to search entities. This is the default driver included with the search package.

## Context
- Package: `packages/search/`
- Study `packages/database/src/Contracts/ConnectionInterface.php` and `packages/database/src/Contracts/QueryBuilderInterface.php` for query building
- Study `packages/database/src/Query/QueryBuilder.php` for how queries are built with where/orderBy/limit
- Study `packages/blog/src/Repository/PostRepository.php` for repository query patterns
- DatabaseSearchDriver implements SearchInterface from task 018
- Uses LIKE queries across fields defined by SearchableInterface
- Applies SearchFilter operators to QueryBuilder where clauses
- Respects field weights for ordering (higher weight = more relevant)

## Requirements (Test Descriptions)
- [ ] `it searches entities using SQL LIKE for partial text matching`
- [ ] `it searches across multiple fields defined by SearchableInterface`
- [ ] `it applies equality filters from SearchCriteria to query`
- [ ] `it applies sorting from SearchCriteria to query results`
- [ ] `it paginates results based on SearchCriteria page and per_page`
- [ ] `it returns SearchResult with total count and matched items`

## Acceptance Criteria
- All requirements have passing tests
- DatabaseSearchDriver in `src/Driver/DatabaseSearchDriver.php`
- Uses QueryBuilderInterface (not concrete) for database access
- Bound in module.php as default SearchInterface implementation
- Supports all filter operators from SearchCriteria (equals, not_equals, greater_than, less_than, in, like)
- Code follows code standards

## Implementation Notes
