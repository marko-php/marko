# Task 039: Category Archive View

**Status**: pending
**Depends on**: 027, 032
**Retry count**: 0

## Description
Create the Latte view template for category archive pages. Shows category name, hierarchy path, and paginated list of posts in the category.

## Context
- Related files: `packages/blog/resources/views/category/show.latte`
- Patterns to follow: Similar to author archive
- Shows breadcrumb path for hierarchical categories

## Requirements (Test Descriptions)
- [ ] `it renders category name as page title`
- [ ] `it displays category hierarchy path as breadcrumbs`
- [ ] `it renders list of posts in category`
- [ ] `it displays post title summary author and date`
- [ ] `it includes pagination component`
- [ ] `it shows message when category has no posts`
- [ ] `it has semantic HTML structure`
- [ ] `it includes proper canonical URL`

## Acceptance Criteria
- All requirements have passing tests
- Template renders category info and posts
- Breadcrumb navigation works
- Pagination works correctly
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
