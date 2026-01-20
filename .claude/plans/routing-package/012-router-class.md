# Task 012: Router Class

**Status**: completed
**Depends on**: 009, 010, 011
**Retry count**: 0

## Description
Create the main Router class that orchestrates route matching, middleware execution, and controller dispatch. This is the primary entry point for handling HTTP requests - it ties together all the routing components.

## Context
- Location: `packages/routing/src/`
- Receives Request, returns Response
- Uses RouteMatcher to find matching route
- Uses MiddlewarePipeline to execute middleware
- Resolves controller through DI container
- Passes extracted parameters to controller method

## Requirements (Test Descriptions)
- [ ] `it accepts RouteCollection in constructor`
- [ ] `it matches request to route`
- [ ] `it returns 404 response when no route matches`
- [ ] `it resolves controller through container`
- [ ] `it invokes controller method`
- [ ] `it passes route parameters to controller method`
- [ ] `it executes middleware pipeline`
- [ ] `it returns response from controller`
- [ ] `it returns response from middleware short-circuit`
- [ ] `it handles controller returning Response object`
- [ ] `it wraps string return in Response object`
- [ ] `it wraps array return in JSON Response`

## Acceptance Criteria
- All requirements have passing tests
- Clean orchestration of components
- Flexible return type handling from controllers
- Proper 404 handling

## Files to Create
```
packages/routing/src/
  Router.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
