# Task 010: Route Matching and Parameter Extraction

**Status**: completed
**Depends on**: 007, 009
**Retry count**: 0

## Description
Create the RouteMatcher class that matches incoming requests to registered routes and extracts parameter values. It converts path patterns like `/posts/{id}` into regex and captures parameter values from the actual request path.

## Context
- Location: `packages/routing/src/`
- Uses RouteDefinition's regex pattern for matching
- Extracts named parameters from matched path
- Returns MatchedRoute (route + extracted parameters) or null
- Handles multiple routes with same prefix correctly

## Requirements (Test Descriptions)
- [ ] `it matches exact static path`
- [ ] `it matches path with single parameter`
- [ ] `it matches path with multiple parameters`
- [ ] `it extracts parameter value from matched path`
- [ ] `it extracts multiple parameter values`
- [ ] `it returns null for non-matching path`
- [ ] `it returns null for wrong HTTP method`
- [ ] `it matches correct route when multiple routes have same prefix`
- [ ] `it returns MatchedRoute with route definition and parameters`
- [ ] `it handles trailing slashes consistently`
- [ ] `it matches root path /`

## Acceptance Criteria
- All requirements have passing tests
- Parameter extraction is accurate
- Matching handles edge cases (trailing slashes, root path)
- Performance is reasonable for moderate number of routes

## Files to Create
```
packages/routing/src/
  RouteMatcher.php
  MatchedRoute.php    # Value object: RouteDefinition + extracted params
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
