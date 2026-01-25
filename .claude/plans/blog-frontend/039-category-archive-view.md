# Task 039: Category Archive View

**Status**: complete
**Depends on**: 027, 032
**Retry count**: 0

## Description
Create the Latte view template for category archive pages. Shows category name, hierarchy path, and paginated list of posts in the category.

## Context
- Related files: `packages/blog/resources/views/category/show.latte`
- Patterns to follow: Similar to author archive
- Shows breadcrumb path for hierarchical categories

## Requirements (Test Descriptions)
- [x] `it renders category name as page title`
- [x] `it displays category hierarchy path as breadcrumbs`
- [x] `it renders list of posts in category`
- [x] `it displays post title summary author and date`
- [x] `it includes pagination component`
- [x] `it shows message when category has no posts`
- [x] `it has semantic HTML structure`
- [x] `it includes proper canonical URL`

## Acceptance Criteria
- All requirements have passing tests
- Template renders category info and posts
- Breadcrumb navigation works
- Pagination works correctly
- Code follows Marko standards

## Implementation Notes
Created category archive view template at `packages/blog/resources/views/category/show.latte` with:
- Breadcrumb navigation showing category hierarchy path with proper aria-labels
- H1 title displaying category name
- Post list with title, summary, author, and formatted date
- Pagination component included via relative path (`../components/pagination.latte`)
- Empty state message when category has no posts
- Optional canonical URL meta tag (template uses `isset()` to handle missing variable gracefully)
- Semantic HTML with nav, ol/li, h1, h2, time elements, and ARIA attributes

Tests created at `packages/blog/tests/Views/CategoryShowTest.php` with 8 passing tests covering all requirements.

Note: Used relative path for pagination include instead of module namespace path (`blog::components/pagination`) because Latte's native include directive doesn't support module namespacing - it would need to go through the custom ModuleTemplateResolver. The relative path works correctly for template includes within the same module.
