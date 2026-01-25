# Task 025: Post Show Controller

**Status**: pending
**Depends on**: 007, 013
**Retry count**: 0

## Description
Update the PostController to handle single post display with comments. Shows full post content, author info, categories, tags, and threaded comments.

## Context
- Related files: `packages/blog/src/Controllers/PostController.php` (exists)
- Patterns to follow: Existing show method, enhance with comments
- Route: GET /blog/{slug}
- Controller must inject interfaces (PostRepositoryInterface, CommentRepositoryInterface), not concrete classes

## Requirements (Test Descriptions)
- [ ] `it returns single post at GET /blog/{slug}`
- [ ] `it returns 404 when post slug not found`
- [ ] `it returns 404 when post is not published`
- [ ] `it includes full post content in response`
- [ ] `it includes author information`
- [ ] `it includes post categories`
- [ ] `it includes post tags`
- [ ] `it includes threaded verified comments`
- [ ] `it renders using view template`

## Acceptance Criteria
- All requirements have passing tests
- Route GET /blog/{slug} returns full post with comments
- Uses PostRepositoryInterface and CommentRepositoryInterface (injected)
- Controllers swappable via Preferences for customization
- Only shows verified comments
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
