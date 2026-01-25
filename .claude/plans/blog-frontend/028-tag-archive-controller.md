# Task 028: Tag Archive Controller

**Status**: complete
**Depends on**: 005, 017
**Retry count**: 0

## Description
Create controller for tag archive pages showing all published posts with a specific tag with pagination.

## Context
- Related files: `packages/blog/src/Controllers/TagController.php`
- Patterns to follow: Similar to category archive controller
- Route: GET /blog/tag/{slug}

## Requirements (Test Descriptions)
- [x] `it injects TagRepositoryInterface and PostRepositoryInterface not concrete classes`
- [x] `it returns paginated posts with tag at GET /blog/tag/{slug}`
- [x] `it returns 404 when tag slug not found`
- [x] `it includes tag name in response`
- [x] `it only includes published posts`
- [x] `it orders posts by published date descending`
- [x] `it accepts page query parameter for pagination`
- [x] `it includes pagination metadata in response`
- [x] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/tag/{slug} works with pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes

### Files Created/Modified

1. **packages/blog/src/Controllers/TagController.php** - New controller for tag archive pages
   - Injects `TagRepositoryInterface`, `PostRepositoryInterface`, `PaginationServiceInterface`, and `ViewInterface`
   - `index(string $slug, int $page = 1)` method with `#[Get('/blog/tag/{slug}')]` route attribute
   - Returns 404 response when tag not found
   - Uses `findPublishedByTag` to get only published posts ordered by published date descending
   - Passes tag and paginated posts to view

2. **packages/blog/src/Repositories/PostRepositoryInterface.php** - Added methods:
   - `findPublishedByTag(int $tagId, int $limit, int $offset): array`
   - `countPublishedByTag(int $tagId): int`

3. **packages/blog/src/Repositories/PostRepository.php** - Implemented the new methods with SQL that:
   - Joins posts with post_tags table
   - Filters by tag_id and published status
   - Orders by published_at DESC
   - Supports pagination via LIMIT/OFFSET

4. **packages/blog/tests/Controllers/TagControllerTest.php** - 9 passing tests covering all requirements

5. **packages/blog/tests/Controllers/PostControllerTest.php** - Added namespace to fix function name collision

### Notes
- The controller follows the same pattern as CategoryController
- All dependencies use interfaces, not concrete classes (DI-friendly)
- The repository handles ordering by published date descending
- View template is `blog::tag/index`
