# Task 024: Post List Controller

**Status**: completed
**Depends on**: 007, 017, 019
**Retry count**: 0

## Description
Create or update the PostController to handle the blog index route with pagination. Shows published posts ordered by date descending, with configurable posts per page.

## Context
- Related files: `packages/blog/src/Controllers/PostController.php` (exists - needs refactor)
- Patterns to follow: Existing controller patterns, uses `#[Get]` attribute
- Route: GET /blog with optional ?page= parameter
- **IMPORTANT:** Existing PostController injects concrete `PostRepository` - must refactor to inject `PostRepositoryInterface` for extensibility

## Requirements (Test Descriptions)
- [x] `it injects PostRepositoryInterface not concrete PostRepository`
- [x] `it returns paginated list of published posts at GET /blog`
- [x] `it orders posts by published date descending`
- [x] `it excludes draft and scheduled posts from listing`
- [x] `it accepts page query parameter for pagination`
- [x] `it defaults to page 1 when no page parameter`
- [x] `it returns 404 for invalid page numbers`
- [x] `it includes pagination metadata in response`
- [x] `it includes post title summary author and date in listing`
- [x] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog works with pagination
- Uses PostRepositoryInterface and PaginationServiceInterface (injected)
- Controllers swappable via Preferences for customization
- Returns proper HTTP responses
- Code follows Marko standards

## Implementation Notes
- Refactored PostController to inject `PostRepositoryInterface` instead of concrete `PostRepository`
- Added `PaginationServiceInterface` dependency for pagination handling
- Added two new methods to `PostRepositoryInterface`: `findPublishedPaginated(int $limit, int $offset): array` and `countPublished(): int`
- Implemented these methods in `PostRepository` with ordering by `published_at DESC`
- Controller validates page numbers (returns 404 for page < 1 or page > totalPages)
- Returns `PaginatedResult` with full metadata (currentPage, totalItems, totalPages, perPage, hasPreviousPage, hasNextPage, pageNumbers)
- Updated test mocks in AuthorControllerTest, CategoryControllerTest, and TagControllerTest to include new interface methods
