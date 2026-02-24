# Task 003: ResourceCollection with Pagination

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Implement ResourceCollection that wraps arrays of entities and integrates with marko/pagination to include pagination metadata in API responses.

## Context
- Package: `packages/api/`
- ResourceCollection implements ResourceCollectionInterface from task 001
- Wraps an array of items using a specified JsonResource class from task 002
- Integrates with pagination interfaces from `packages/pagination/`
- Study `packages/pagination/src/Contracts/PaginatorInterface.php` for pagination data structure
- Study `packages/admin-api/src/Response/ApiResponse.php` `paginated()` method for existing pattern
- Output format: `{ "data": [...], "meta": { "page": 1, "per_page": 15, "total": 100, "total_pages": 7 } }`

## Requirements (Test Descriptions)
- [ ] `it wraps an array of items using specified resource class`
- [ ] `it serializes all items via their resource toArray method`
- [ ] `it includes pagination metadata when paginator is provided`
- [ ] `it returns JSON response with data and meta keys via toResponse`
- [ ] `it supports additional meta data via additional method`

## Acceptance Criteria
- All requirements have passing tests
- ResourceCollection is in `src/Resource/ResourceCollection.php`
- Pagination meta includes: page, per_page, total, total_pages
- Additional meta merges with pagination meta
- Update module.php bindings if needed
- Code follows code standards

## Implementation Notes
