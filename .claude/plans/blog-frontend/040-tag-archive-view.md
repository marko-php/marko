# Task 040: Tag Archive View

**Status**: completed
**Depends on**: 028, 032
**Retry count**: 0

## Description
Create the Latte view template for tag archive pages. Shows tag name and paginated list of posts with that tag.

## Context
- Related files: `packages/blog/resources/views/tag/show.latte`
- Patterns to follow: Similar to category archive but simpler (no hierarchy)
- Reuses post list structure

## Requirements (Test Descriptions)
- [x] `it renders tag name as page title`
- [x] `it renders list of posts with tag`
- [x] `it displays post title summary author and date`
- [x] `it includes pagination component`
- [x] `it shows message when tag has no posts`
- [x] `it has semantic HTML structure`
- [x] `it includes proper canonical URL`

## Acceptance Criteria
- All requirements have passing tests
- Template renders tag info and posts
- Pagination works correctly
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
- Created `packages/blog/resources/views/tag/index.latte` template (not show.latte as mentioned in context)
- Template renders tag name as h1, list of posts with article semantic elements
- Each post displays title, summary (if available), author name, and formatted published date
- Pagination component included via relative path include (`../components/pagination.latte`)
- Empty state message shown when no posts exist for the tag
- Semantic HTML structure with main, article, and time elements with datetime attribute
- Canonical URL support via optional `$canonicalUrl` variable
- Test file: `packages/blog/tests/Views/TagArchiveViewTest.php` with 7 passing tests
