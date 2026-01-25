# Task 032: Pagination View Component

**Status**: pending
**Depends on**: 017
**Retry count**: 0

## Description
Create a reusable Latte view component for pagination controls. Displays previous/next links and numbered page links with proper accessibility and styling hooks.

## Context
- Related files: `packages/blog/resources/views/components/pagination.latte`
- Patterns to follow: Latte component/partial pattern
- Used across all list views (posts, archives, search)

## Requirements (Test Descriptions)
- [ ] `it renders previous link when not on first page`
- [ ] `it hides previous link on first page`
- [ ] `it renders next link when not on last page`
- [ ] `it hides next link on last page`
- [ ] `it renders numbered page links`
- [ ] `it highlights current page`
- [ ] `it shows ellipsis for large page ranges`
- [ ] `it includes proper href with page parameter`
- [ ] `it preserves existing query parameters in links`
- [ ] `it renders nothing when only one page`
- [ ] `it has semantic HTML with nav and aria labels`

## Acceptance Criteria
- All requirements have passing tests
- Component is styling-agnostic (CSS classes only)
- Works with PaginatedResult DTO
- Accessible with proper ARIA attributes
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
