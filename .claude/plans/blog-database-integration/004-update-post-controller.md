# Task 004: Update PostController to Use Repository

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Update the existing PostController to inject PostRepository and use it to fetch posts from the database. Routes should return simple output (var_dump or formatted echo) showing the post data.

## Context
- Related files:
  - `packages/blog/src/Controllers/PostController.php` (to modify)
  - `packages/blog/src/Repositories/PostRepository.php` (inject this)
  - `packages/blog/tests/Controllers/PostControllerTest.php` (to update)
- Patterns to follow: Constructor injection, Response usage from routing package

## Requirements (Test Descriptions)
- [x] `it injects PostRepository via constructor`
- [x] `it returns response with all posts data on index route`
- [x] `it returns response with single post data on show route`
- [x] `it returns 404 response when post slug not found`
- [x] `it maintains existing route attributes for GET /blog and GET /blog/{slug}`

## Acceptance Criteria
- All requirements have passing tests
- Controller uses dependency injection (no static calls)
- Routes return Response objects with simple text output
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
