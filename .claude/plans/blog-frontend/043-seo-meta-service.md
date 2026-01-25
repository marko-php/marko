# Task 043: SEO Meta Service

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Create a service for generating SEO meta tags: canonical URLs, meta descriptions, and pagination link headers. Centralizes SEO logic for all blog pages.

## Context
- Related files: `packages/blog/src/Services/SeoMetaService.php`
- Patterns to follow: Interface/implementation split
- Used by views to generate proper meta tags

## Requirements (Test Descriptions)
- [ ] `it generates canonical URL for post page`
- [ ] `it generates canonical URL for archive pages`
- [ ] `it generates canonical URL for search results`
- [ ] `it generates meta description from post summary`
- [ ] `it generates meta description for archive pages`
- [ ] `it truncates meta description to 160 characters`
- [ ] `it generates rel prev link for paginated pages`
- [ ] `it generates rel next link for paginated pages`
- [ ] `it omits prev link on first page`
- [ ] `it omits next link on last page`
- [ ] `it generates page title with site name`

## Acceptance Criteria
- All requirements have passing tests
- SeoMetaServiceInterface defined for Preference swapping
- SeoMetaService implements interface
- Swappable for custom SEO implementations
- Handles edge cases (empty summary, single page)
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
