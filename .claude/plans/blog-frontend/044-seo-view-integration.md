# Task 044: SEO View Integration

**Status**: pending
**Depends on**: 033, 037, 038, 039, 040, 041, 043
**Retry count**: 0

## Description
Integrate SEO meta tags into all blog view templates. Add canonical URLs, meta descriptions, and pagination links to page heads.

## Context
- Related files: All view templates in `packages/blog/resources/views/`
- Patterns to follow: Latte blocks for head content
- Must work with base layout's head section

## Requirements (Test Descriptions)
- [ ] `it includes canonical link in post page head`
- [ ] `it includes meta description in post page head`
- [ ] `it includes canonical link in archive pages`
- [ ] `it includes meta description in archive pages`
- [ ] `it includes rel prev and next for paginated pages`
- [ ] `it includes proper page title in title tag`
- [ ] `it includes og:title meta tag`
- [ ] `it includes og:description meta tag`
- [ ] `it includes og:url meta tag`
- [ ] `it includes og:type meta tag`

## Acceptance Criteria
- All requirements have passing tests
- All blog pages have proper SEO meta tags
- Meta tags render in HTML head section
- Open Graph tags included for social sharing
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
