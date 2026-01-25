# Task 017: Pagination Service

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a generic pagination service that calculates page metadata (total pages, has prev/next, page numbers to display) and generates paginated result sets. Used across all list views.

## Context
- Related files: `packages/blog/src/Services/PaginationService.php`, `packages/blog/src/Dto/PaginatedResult.php`
- Patterns to follow: Interface/implementation split, immutable DTOs
- Returns both items and pagination metadata

## Requirements (Test Descriptions)
- [ ] `it calculates total pages from total items and per page`
- [ ] `it determines if has previous page`
- [ ] `it determines if has next page`
- [ ] `it returns current page number`
- [ ] `it calculates offset for database query`
- [ ] `it generates array of page numbers for display`
- [ ] `it limits displayed page numbers with ellipsis logic`
- [ ] `it uses configured posts_per_page from BlogConfig`
- [ ] `it creates PaginatedResult with items and metadata`
- [ ] `it handles edge case of zero total items`

## PaginatedResult DTO

```php
/**
 * Immutable DTO containing paginated items and metadata.
 * Used by all list views (posts, archives, search results).
 */
readonly class PaginatedResult
{
    /**
     * @param array $items The items for the current page
     * @param int $currentPage Current page number (1-indexed)
     * @param int $totalItems Total number of items across all pages
     * @param int $perPage Number of items per page
     * @param int $totalPages Total number of pages
     * @param bool $hasPreviousPage Whether a previous page exists
     * @param bool $hasNextPage Whether a next page exists
     * @param array $pageNumbers Page numbers to display (with null for ellipsis)
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $totalItems,
        public int $perPage,
        public int $totalPages,
        public bool $hasPreviousPage,
        public bool $hasNextPage,
        public array $pageNumbers,
    ) {}

    /**
     * Get the previous page number, or null if on first page.
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage ? $this->currentPage - 1 : null;
    }

    /**
     * Get the next page number, or null if on last page.
     */
    public function getNextPage(): ?int
    {
        return $this->hasNextPage ? $this->currentPage + 1 : null;
    }

    /**
     * Check if pagination should be displayed (more than one page).
     */
    public function shouldShowPagination(): bool
    {
        return $this->totalPages > 1;
    }

    /**
     * Check if the result set is empty.
     */
    public function isEmpty(): bool
    {
        return $this->totalItems === 0;
    }
}
```

## PaginationServiceInterface

```php
interface PaginationServiceInterface
{
    /**
     * Create a paginated result from items and total count.
     *
     * @param array $items Items for the current page
     * @param int $totalItems Total item count (across all pages)
     * @param int $currentPage Current page number (1-indexed)
     * @param int|null $perPage Items per page (null = use config default)
     */
    public function paginate(
        array $items,
        int $totalItems,
        int $currentPage,
        ?int $perPage = null,
    ): PaginatedResult;

    /**
     * Calculate the offset for database queries.
     *
     * @param int $page Page number (1-indexed)
     * @param int|null $perPage Items per page (null = use config default)
     */
    public function calculateOffset(
        int $page,
        ?int $perPage = null,
    ): int;

    /**
     * Get the configured items per page.
     */
    public function getPerPage(): int;
}
```

## Page Numbers with Ellipsis

The `pageNumbers` array contains page numbers to display, with `null` representing ellipsis:

```php
// Example: Page 5 of 20
$pageNumbers = [1, null, 4, 5, 6, null, 20];
// Renders as: 1 ... 4 5 6 ... 20

// Example: Page 1 of 5
$pageNumbers = [1, 2, 3, 4, 5];
// Renders as: 1 2 3 4 5

// Example: Page 2 of 20
$pageNumbers = [1, 2, 3, null, 20];
// Renders as: 1 2 3 ... 20
```

## Acceptance Criteria
- All requirements have passing tests
- PaginationServiceInterface defined
- PaginatedResult DTO with readonly properties
- Page number generation includes ellipsis logic for large page counts
- Handles all edge cases (first page, last page, empty)
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
