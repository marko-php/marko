# Task 031: Search Controller

**Status**: pending
**Depends on**: 017, 018
**Retry count**: 0

## Description
Create controller for blog search. Accepts search query, returns paginated results sorted by relevance, displayed in same format as post list.

## Context
- Related files: `packages/blog/src/Controllers/SearchController.php`
- Patterns to follow: Similar to post list but with search filtering
- Route: GET /blog/search?q={term}

## Requirements (Test Descriptions)
- [ ] `it returns search results at GET /blog/search`
- [ ] `it requires q query parameter`
- [ ] `it returns empty results when q is empty`
- [ ] `it returns posts matching search term`
- [ ] `it orders results by relevance score`
- [ ] `it accepts page query parameter for pagination`
- [ ] `it includes pagination metadata in response`
- [ ] `it includes search term in response for display`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/search works with query and pagination
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
