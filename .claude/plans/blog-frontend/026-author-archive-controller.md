# Task 026: Author Archive Controller

**Status**: pending
**Depends on**: 003, 017
**Retry count**: 0

## Description
Create controller for author archive pages showing all published posts by a specific author with pagination. Displays author bio and post listing.

## Context
- Related files: `packages/blog/src/Controllers/AuthorController.php`
- Patterns to follow: Similar to post list controller
- Route: GET /blog/author/{slug}

## Requirements (Test Descriptions)
- [ ] `it injects AuthorRepositoryInterface and PostRepositoryInterface not concrete classes`
- [ ] `it returns paginated posts by author at GET /blog/author/{slug}`
- [ ] `it returns 404 when author slug not found`
- [ ] `it includes author name email and bio in response`
- [ ] `it only includes published posts`
- [ ] `it orders posts by published date descending`
- [ ] `it accepts page query parameter for pagination`
- [ ] `it includes pagination metadata in response`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/author/{slug} works with pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
