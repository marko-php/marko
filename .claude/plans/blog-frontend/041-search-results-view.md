# Task 041: Search Results View

**Status**: pending
**Depends on**: 031, 032, 046
**Retry count**: 0

## Description
Create the Latte view template for search results. Shows search form, current query, result count, and paginated matching posts.

## Context
- Related files: `packages/blog/resources/views/search/index.latte`
- Patterns to follow: Similar to post list with search form header
- Preserves search query in pagination links

## Requirements (Test Descriptions)
- [ ] `it includes search bar component with current query`
- [ ] `it displays result count for query`
- [ ] `it renders matching posts`
- [ ] `it displays post title summary author and date`
- [ ] `it includes pagination component`
- [ ] `it preserves search query in pagination links`
- [ ] `it shows no results message when empty`
- [ ] `it has semantic HTML structure`
- [ ] `it includes search input with label`

## Acceptance Criteria
- All requirements have passing tests
- Search form pre-filled with query
- Pagination maintains query parameter
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
