# Task 027: Category Archive Controller

**Status**: pending
**Depends on**: 004, 017
**Retry count**: 0

## Description
Create controller for category archive pages showing all published posts in a specific category with pagination. Displays category name and hierarchy.

## Context
- Related files: `packages/blog/src/Controllers/CategoryController.php`
- Patterns to follow: Similar to author archive controller
- Route: GET /blog/category/{slug}

## Requirements (Test Descriptions)
- [ ] `it injects CategoryRepositoryInterface and PostRepositoryInterface not concrete classes`
- [ ] `it returns paginated posts in category at GET /blog/category/{slug}`
- [ ] `it returns 404 when category slug not found`
- [ ] `it includes category name and path in response`
- [ ] `it only includes published posts`
- [ ] `it orders posts by published date descending`
- [ ] `it accepts page query parameter for pagination`
- [ ] `it includes pagination metadata in response`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/category/{slug} works with pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
