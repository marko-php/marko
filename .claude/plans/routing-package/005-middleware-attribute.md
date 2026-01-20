# Task 005: Middleware Attribute and Interface

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the Middleware attribute for applying middleware at class or method level, and the MiddlewareInterface that all middleware must implement. Middleware intercepts requests before/after the controller.

## Context
- Location: `packages/routing/src/Attributes/` and `packages/routing/src/Middleware/`
- #[Middleware] can target both classes and methods
- Class-level middleware applies to all methods in controller
- Method-level middleware applies only to that method
- MiddlewareInterface defines handle(Request, callable): Response

## Requirements (Test Descriptions)
- [ ] `Middleware attribute accepts single middleware class`
- [ ] `Middleware attribute accepts array of middleware classes`
- [ ] `Middleware attribute can target both classes and methods`
- [ ] `MiddlewareInterface defines handle method signature`
- [ ] `handle receives Request and next callable`
- [ ] `handle returns Response`
- [ ] `middleware can call next to continue pipeline`
- [ ] `middleware can return early to short-circuit`

## Acceptance Criteria
- All requirements have passing tests
- Clear interface contract for middleware
- Attribute supports both single and multiple middleware

## Files to Create
```
packages/routing/src/Attributes/
  Middleware.php            # #[Middleware(AuthMiddleware::class)]
packages/routing/src/Middleware/
  MiddlewareInterface.php   # Contract for middleware classes
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
