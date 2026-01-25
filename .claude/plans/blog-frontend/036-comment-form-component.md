# Task 036: Comment Form Component

**Status**: completed
**Depends on**: 016
**Retry count**: 0

## Description
Create a Latte component for the comment submission form. Includes fields for name, email, content, optional parent_id for replies, and honeypot field.

## Context
- Related files: `packages/blog/resources/views/components/comment-form.latte`
- Patterns to follow: Standard HTML form with Latte
- Honeypot field hidden via CSS
- CSRF protection is OPTIONAL - check if `marko/csrf` is installed at runtime
- If CsrfInterface is available, include token; if not, form works without it

## Requirements (Test Descriptions)
- [ ] `it renders form with POST action to comment endpoint`
- [ ] `it includes name input field with label`
- [ ] `it includes email input field with label`
- [ ] `it includes content textarea with label`
- [ ] `it includes hidden parent_id field for replies`
- [ ] `it includes honeypot field hidden by CSS`
- [ ] `it includes submit button`
- [ ] `it shows validation error messages when provided`
- [ ] `it preserves input values on validation failure`
- [ ] `it has proper form accessibility labels`
- [ ] `it includes CSRF token when marko/csrf is installed`
- [ ] `it works without CSRF when marko/csrf is not installed`

## Acceptance Criteria
- All requirements have passing tests
- Form submits to correct endpoint
- Honeypot field properly hidden
- Accessible form structure
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
