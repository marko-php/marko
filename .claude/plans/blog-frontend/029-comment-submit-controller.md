# Task 029: Comment Submit Controller

**Status**: pending
**Depends on**: 014, 015, 016, 020
**Retry count**: 0

## Description
Create controller for comment submission. Handles POST requests, validates input (including honeypot), checks rate limits, and initiates email verification or auto-approves based on browser token.

## Context
- Related files: `packages/blog/src/Controllers/CommentController.php`
- Patterns to follow: POST endpoint with validation
- Route: POST /blog/{slug}/comment
- CSRF validation is OPTIONAL - only validate if `marko/csrf` is installed

## Requirements (Test Descriptions)
- [ ] `it accepts comment submission at POST /blog/{slug}/comment`
- [ ] `it returns 404 when post slug not found`
- [ ] `it returns 404 when post is not published`
- [ ] `it validates author_name is required`
- [ ] `it validates author_email is required and valid format`
- [ ] `it validates content is required and has minimum length`
- [ ] `it rejects submission when honeypot field is filled`
- [ ] `it validates CSRF token when marko/csrf is installed`
- [ ] `it skips CSRF validation when marko/csrf is not installed`
- [ ] `it rejects submission when rate limit exceeded`
- [ ] `it returns rate limit wait time when rate limited`
- [ ] `it auto-approves comment when valid browser token exists`
- [ ] `it creates pending comment and sends verification email when no token`
- [ ] `it accepts optional parent_id for threaded replies`
- [ ] `it validates parent comment belongs to same post`
- [ ] `it validates reply does not exceed configured max depth`
- [ ] `it dispatches CommentCreated event`
- [ ] `it returns success response with next steps`

## Acceptance Criteria
- All requirements have passing tests
- Route POST /blog/{slug}/comment handles all cases
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Returns appropriate HTTP status codes
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
