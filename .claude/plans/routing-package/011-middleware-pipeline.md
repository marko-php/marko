# Task 011: Middleware Pipeline

**Status**: pending
**Depends on**: 003, 005
**Retry count**: 0

## Description
Create the MiddlewarePipeline class that executes middleware in order, allowing each to process the request and optionally pass to the next. The pipeline wraps the controller invocation as the final handler.

## Context
- Location: `packages/routing/src/Middleware/`
- Middleware executed in order: first registered runs first on request, last on response
- Each middleware receives Request and "next" callable
- Final handler is the controller method
- Middleware resolved through DI container for dependency injection

## Requirements (Test Descriptions)
- [ ] `it executes single middleware`
- [ ] `it executes multiple middleware in order`
- [ ] `it passes request through middleware chain`
- [ ] `it executes final handler after all middleware`
- [ ] `it allows middleware to short-circuit by returning early`
- [ ] `it allows middleware to modify request before passing`
- [ ] `it allows middleware to modify response after receiving`
- [ ] `it resolves middleware classes through container`
- [ ] `it handles empty middleware array (just runs handler)`
- [ ] `it propagates exceptions from middleware`
- [ ] `it propagates exceptions from handler`

## Acceptance Criteria
- All requirements have passing tests
- Clean stack-based execution
- Middleware can intercept both request and response
- DI integration for middleware construction

## Files to Create
```
packages/routing/src/Middleware/
  MiddlewarePipeline.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
