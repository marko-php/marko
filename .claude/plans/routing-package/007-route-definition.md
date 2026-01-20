# Task 007: RouteDefinition Value Object

**Status**: completed
**Depends on**: 004, 005
**Retry count**: 0

## Description
Create the RouteDefinition value object that represents a discovered route. It holds all information needed to match and dispatch a route: HTTP method, path, controller class, method name, middleware stack, and parameter names.

## Context
- Location: `packages/routing/src/`
- Immutable value object (readonly class)
- Created by route discovery, used by router
- Stores extracted parameter names from path (e.g., {id} -> ['id'])
- Middleware combines class-level and method-level middleware

## Requirements (Test Descriptions)
- [ ] `RouteDefinition stores HTTP method`
- [ ] `RouteDefinition stores path pattern`
- [ ] `RouteDefinition stores controller class name`
- [ ] `RouteDefinition stores method name`
- [ ] `RouteDefinition stores middleware array`
- [ ] `RouteDefinition extracts parameter names from path`
- [ ] `RouteDefinition with path /posts/{id} has parameter id`
- [ ] `RouteDefinition with path /posts/{id}/comments/{commentId} has parameters id and commentId`
- [ ] `RouteDefinition with no parameters has empty parameter array`
- [ ] `RouteDefinition generates regex pattern for matching`
- [ ] `RouteDefinition is readonly`

## Acceptance Criteria
- All requirements have passing tests
- Parameter extraction handles multiple parameters
- Regex pattern correctly matches paths with parameters
- Clear property types and documentation

## Files to Create
```
packages/routing/src/
  RouteDefinition.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
