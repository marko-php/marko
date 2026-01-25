# Task 038: Author Archive View

**Status**: pending
**Depends on**: 026, 032
**Retry count**: 0

## Description
Create the Latte view template for author archive pages. Shows author information and paginated list of their posts.

## Context
- Related files: `packages/blog/resources/views/author/show.latte`
- Patterns to follow: Similar to post list but with author header
- Reuses post list item structure and pagination component

## Requirements (Test Descriptions)
- [ ] `it renders author name as page title`
- [ ] `it displays author bio`
- [ ] `it displays author email`
- [ ] `it renders list of posts by author`
- [ ] `it displays post title summary and date`
- [ ] `it includes pagination component`
- [ ] `it shows message when author has no posts`
- [ ] `it has semantic HTML structure`
- [ ] `it includes proper canonical URL`

## Acceptance Criteria
- All requirements have passing tests
- Template renders author info and posts
- Pagination works correctly
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
