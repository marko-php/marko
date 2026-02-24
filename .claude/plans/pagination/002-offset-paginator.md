# Task 002: OffsetPaginator with Page Calculation Logic

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement `OffsetPaginator` -- the traditional offset/limit paginator that calculates page numbers from a total count. This is the standard paginator for web pages where users navigate by page number.

## Context
- Class: `Marko\Pagination\OffsetPaginator`
- Implements `PaginatorInterface`
- Constructor takes: items array, total count, per-page, current page
- Calculates lastPage as `ceil(total / perPage)`
- Validates inputs: page must be >= 1, perPage must be >= 1, total must be >= 0
- Throws PaginationException for invalid inputs
- previousPage() returns null on page 1
- nextPage() returns null on last page
- Works correctly for edge cases: empty results (0 total), single page, single item

## Requirements (Test Descriptions)
- [ ] `it creates OffsetPaginator with items, total, perPage, and currentPage`
- [ ] `it calculates lastPage from total and perPage`
- [ ] `it returns hasMorePages true when currentPage is less than lastPage`
- [ ] `it returns previousPage as null on first page and nextPage as null on last page`
- [ ] `it handles empty result set with zero total`
- [ ] `it handles single-page result where total equals perPage`
- [ ] `it throws PaginationException for invalid page number (zero, negative)`

## Acceptance Criteria
- OffsetPaginator implements PaginatorInterface
- Constructor uses property promotion with readonly where appropriate
- lastPage() returns correct ceiling calculation (e.g., 150 items / 15 per page = 10 pages)
- hasMorePages() returns true when currentPage < lastPage
- previousPage() returns currentPage - 1, or null when on page 1
- nextPage() returns currentPage + 1, or null when on last page
- items() returns the items array passed to constructor
- Throws PaginationException::invalidPage() for page < 1
- Throws PaginationException::invalidPerPage() for perPage < 1
- Empty results (total = 0) handled gracefully: lastPage = 1, hasMorePages = false

## Implementation Notes
(Left blank)
