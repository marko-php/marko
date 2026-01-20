# Task 009: Route Collection and Conflict Detection

**Status**: completed
**Depends on**: 002, 007, 008
**Retry count**: 0

## Description
Create the RouteCollection class that stores all discovered routes and detects conflicts. When two routes have the same method+path combination without one being a Preference override, it throws RouteConflictException with helpful details.

## Context
- Location: `packages/routing/src/`
- Routes indexed by "METHOD:path" for quick lookup
- Conflict detection runs at registration time (fail fast)
- Stores routes from all modules
- Will be extended in Task 013 to handle Preference inheritance

## Requirements (Test Descriptions)
- [ ] `it stores RouteDefinition objects`
- [ ] `it indexes routes by method and path`
- [ ] `it retrieves route by method and path`
- [ ] `it returns null for non-existent route`
- [ ] `it throws RouteConflictException for duplicate GET routes`
- [ ] `it throws RouteConflictException for duplicate POST routes`
- [ ] `it allows same path with different methods`
- [ ] `RouteConflictException includes both controller class names`
- [ ] `RouteConflictException includes the conflicting path`
- [ ] `it returns all routes as array`
- [ ] `it returns routes filtered by HTTP method`

## Acceptance Criteria
- All requirements have passing tests
- Conflicts detected at registration, not at request time
- Error messages clearly identify both conflicting routes
- Collection is iterable

## Files to Create
```
packages/routing/src/
  RouteCollection.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
