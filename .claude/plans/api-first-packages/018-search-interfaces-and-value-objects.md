# Task 018: Search Interfaces and Value Objects

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/search package scaffolding with search interfaces, criteria, result value objects, and exceptions. This establishes a generic search abstraction that can be backed by database, Elasticsearch, Meilisearch, or other engines.

## Context
- New package at `packages/search/`
- Namespace: `Marko\Search`
- Depends on: marko/core, marko/database, marko/pagination, marko/config
- Study `packages/pagination/src/Contracts/PaginatorInterface.php` for pagination integration
- Study `packages/blog/src/Service/PostSearchService.php` if it exists — this package generalizes blog search
- Study `packages/validation/src/Validation/ValidationErrors.php` for collection-like value objects
- SearchableInterface: entities declare which fields are searchable and their weights
- SearchCriteria: query string, filters, sort orders, page/per_page
- SearchResult: items array, total count, query metadata

## Requirements (Test Descriptions)
- [ ] `it defines SearchInterface with search method accepting query and criteria`
- [ ] `it defines SearchableInterface for entities declaring searchable fields and weights`
- [ ] `it defines SearchCriteria value object with query, filters, sorting, and pagination`
- [ ] `it defines SearchResult value object with items, total, and query metadata`
- [ ] `it defines SearchFilter value object with field, operator, and value`
- [ ] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces in `src/Contracts/`
- Value objects in `src/Value/`
- Exceptions in `src/Exceptions/`
- Config at `config/search.php` with default driver, per_page settings
- SearchCriteria supports: equals, not_equals, greater_than, less_than, in, like operators
- Code follows code standards

## Implementation Notes
