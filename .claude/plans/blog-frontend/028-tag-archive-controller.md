# Task 028: Tag Archive Controller

**Status**: pending
**Depends on**: 005, 017
**Retry count**: 0

## Description
Create controller for tag archive pages showing all published posts with a specific tag with pagination.

## Context
- Related files: `packages/blog/src/Controllers/TagController.php`
- Patterns to follow: Similar to category archive controller
- Route: GET /blog/tag/{slug}

## Requirements (Test Descriptions)
- [ ] `it injects TagRepositoryInterface and PostRepositoryInterface not concrete classes`
- [ ] `it returns paginated posts with tag at GET /blog/tag/{slug}`
- [ ] `it returns 404 when tag slug not found`
- [ ] `it includes tag name in response`
- [ ] `it only includes published posts`
- [ ] `it orders posts by published date descending`
- [ ] `it accepts page query parameter for pagination`
- [ ] `it includes pagination metadata in response`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/tag/{slug} works with pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
