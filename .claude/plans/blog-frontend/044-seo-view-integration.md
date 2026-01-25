# Task 044: SEO View Integration

**Status**: completed
**Depends on**: 033, 037, 038, 039, 040, 041, 043
**Retry count**: 0

## Description
Integrate SEO meta tags into all blog view templates. Add canonical URLs, meta descriptions, and pagination links to page heads.

## Context
- Related files: All view templates in `packages/blog/resources/views/`
- Patterns to follow: Latte blocks for head content
- Must work with base layout's head section

## Requirements (Test Descriptions)
- [x] `it includes canonical link in post page head`
- [x] `it includes meta description in post page head`
- [x] `it includes canonical link in archive pages`
- [x] `it includes meta description in archive pages`
- [x] `it includes rel prev and next for paginated pages`
- [x] `it includes proper page title in title tag`
- [x] `it includes og:title meta tag`
- [x] `it includes og:description meta tag`
- [x] `it includes og:url meta tag`
- [x] `it includes og:type meta tag`

## Acceptance Criteria
- All requirements have passing tests
- All blog pages have proper SEO meta tags
- Meta tags render in HTML head section
- Open Graph tags included for social sharing
- Code follows Marko standards

## Implementation Notes
SEO meta tags integrated into blog view templates:

1. **Post show template** (`post/show.latte`): Added support for canonical URL, meta description, page title, and Open Graph tags (og:title, og:description, og:url, og:type). Default ogType is "article" for posts.

2. **Category archive template** (`category/show.latte`): Added support for canonical URL, meta description, page title, Open Graph tags, and rel prev/next pagination links. Default ogType is "website".

3. **Tag archive template** (`tag/index.latte`): Added support for canonical URL, meta description, page title, Open Graph tags, and rel prev/next pagination links. Default ogType is "website".

4. **Author archive template** (`author/show.latte`): Added support for canonical URL, meta description, page title, Open Graph tags, and rel prev/next pagination links. Default ogType is "website".

All SEO variables are passed from controllers to templates and rendered conditionally when present. Tests verify:
- Canonical links render correctly for both post and archive pages
- Meta descriptions render in the HTML head
- Pagination rel prev/next links work for paginated archives
- Title tags include the page title with site name suffix
- Open Graph tags (og:title, og:description, og:url, og:type) render correctly
