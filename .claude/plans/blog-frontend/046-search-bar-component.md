# Task 046: Search Bar Component

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a reusable Latte search bar component that can be placed anywhere (header, sidebar, blog index page). Submits to /blog/search with the query. WordPress-style placement flexibility.

## Context
- Related files: `packages/blog/resources/views/components/search-bar.latte`
- Patterns to follow: Reusable Latte component like pagination
- Form submits GET to /blog/search?q={term}

## Requirements (Test Descriptions)
- [ ] `it renders search input with label`
- [ ] `it renders submit button`
- [ ] `it submits form via GET to /blog/search`
- [ ] `it includes query parameter as q`
- [ ] `it pre-fills input with current query when provided`
- [ ] `it has proper accessibility labels`
- [ ] `it has placeholder text`
- [ ] `it is styling-agnostic with CSS classes only`
- [ ] `it can be included in any template`

## Acceptance Criteria
- All requirements have passing tests
- Component works standalone
- Can be included in header, sidebar, or any page
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
