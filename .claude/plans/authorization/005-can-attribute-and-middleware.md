# Task 005: #[Can] Attribute and AuthorizationMiddleware

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the `#[Can]` route attribute and `AuthorizationMiddleware` that reads it via reflection. This enables declarative authorization on route methods: `#[Can('update', Post::class)]`. The middleware resolves the Gate and checks the ability before the controller executes.

## Context
- Related files: `packages/admin-auth/src/Attributes/RequiresPermission.php` (pattern), `packages/admin-auth/src/Middleware/AdminAuthMiddleware.php` (pattern), `packages/routing/src/Middleware/MiddlewareInterface.php`
- `#[Can]` is a method-level attribute with ability name and optional entity class
- Middleware receives controller/action in constructor (like AdminAuthMiddleware)
- When entity class is specified on the attribute, middleware creates a "class-level" check (no specific instance)
- For instance-level checks, controllers should call `Gate::authorize()` directly
- Patterns to follow: MiddlewareInterface, reflection-based attribute reading, JSON vs redirect response handling

## Requirements (Test Descriptions)
- [ ] `it creates Can attribute with ability name`
- [ ] `it creates Can attribute with ability and entity class`
- [ ] `it allows request when gate allows the ability`
- [ ] `it returns 403 when gate denies the ability`
- [ ] `it returns JSON 403 for API requests when denied`
- [ ] `it skips authorization when no Can attribute is present`
- [ ] `it reads Can attribute from controller method via reflection`
- [ ] `it returns 401 when user is not authenticated`

## Acceptance Criteria
- All requirements have passing tests
- Middleware follows MiddlewareInterface contract
- Response format matches request type (JSON for API, plain for web)
- Attribute is properly targeted to methods only

## Implementation Notes
(Left blank - filled in by programmer during implementation)
