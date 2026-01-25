# Task 027: Category Archive Controller

**Status**: completed
**Depends on**: 004, 017
**Retry count**: 0

## Description
Create controller for category archive pages showing all published posts in a specific category with pagination. Displays category name and hierarchy.

## Context
- Related files: `packages/blog/src/Controllers/CategoryController.php`
- Patterns to follow: Similar to author archive controller
- Route: GET /blog/category/{slug}

## Requirements (Test Descriptions)
- [x] `it injects CategoryRepositoryInterface and PostRepositoryInterface not concrete classes`
- [x] `it returns paginated posts in category at GET /blog/category/{slug}`
- [x] `it returns 404 when category slug not found`
- [x] `it includes category name and path in response`
- [x] `it only includes published posts`
- [x] `it orders posts by published date descending`
- [x] `it accepts page query parameter for pagination`
- [x] `it includes pagination metadata in response`
- [x] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/category/{slug} works with pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes
Created CategoryController at packages/blog/src/Controllers/CategoryController.php with:
- Constructor injection of CategoryRepositoryInterface, PostRepositoryInterface, PaginationServiceInterface, ViewInterface
- GET /blog/category/{slug} route via #[Get] attribute
- show() method that accepts slug and page parameters
- Returns 404 Response when category not found
- Passes category, path (hierarchy), and paginated posts to view
- Uses findPublishedByCategory/countPublishedByCategory for filtering published posts
- Repository methods handle ordering by published_at DESC

Also extended PostRepositoryInterface with:
- findPublishedByCategory(int $categoryId, int $limit, int $offset): array
- countPublishedByCategory(int $categoryId): int

And implemented these methods in PostRepository.
