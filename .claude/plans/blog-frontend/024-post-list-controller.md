# Task 024: Post List Controller

**Status**: pending
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
- [ ] `it injects PostRepositoryInterface not concrete PostRepository`
- [ ] `it returns paginated list of published posts at GET /blog`
- [ ] `it orders posts by published date descending`
- [ ] `it excludes draft and scheduled posts from listing`
- [ ] `it accepts page query parameter for pagination`
- [ ] `it defaults to page 1 when no page parameter`
- [ ] `it returns 404 for invalid page numbers`
- [ ] `it includes pagination metadata in response`
- [ ] `it includes post title summary author and date in listing`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog works with pagination
- Uses PostRepositoryInterface and PaginationServiceInterface (injected)
- Controllers swappable via Preferences for customization
- Returns proper HTTP responses
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
