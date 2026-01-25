# Task 040: Tag Archive View

**Status**: pending
**Depends on**: 028, 032
**Retry count**: 0

## Description
Create the Latte view template for tag archive pages. Shows tag name and paginated list of posts with that tag.

## Context
- Related files: `packages/blog/resources/views/tag/show.latte`
- Patterns to follow: Similar to category archive but simpler (no hierarchy)
- Reuses post list structure

## Requirements (Test Descriptions)
- [ ] `it renders tag name as page title`
- [ ] `it renders list of posts with tag`
- [ ] `it displays post title summary author and date`
- [ ] `it includes pagination component`
- [ ] `it shows message when tag has no posts`
- [ ] `it has semantic HTML structure`
- [ ] `it includes proper canonical URL`

## Acceptance Criteria
- All requirements have passing tests
- Template renders tag info and posts
- Pagination works correctly
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
