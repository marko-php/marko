# Task 014: Application Integration

**Status**: completed
**Depends on**: 012, 013
**Retry count**: 0

## Description
Integrate the routing system with marko/core's Application class. Create a RouterServiceProvider or hook that discovers routes during boot, registers the Router in the container, and provides a clean API for handling requests.

## Context
- Location: `packages/routing/src/`
- Router should be available via container after boot
- Route discovery runs during Application::boot()
- Consider how routing package registers itself (module.php bindings or observer)
- Demo app's index.php should be able to: `$app->getRouter()->handle($request)->send()`

## Requirements (Test Descriptions)
- [ ] `it registers Router in container during boot`
- [ ] `it discovers routes from all loaded modules`
- [ ] `it resolves RouteCollection through container`
- [ ] `it applies Preference inheritance during discovery`
- [ ] `Application provides getRouter method`
- [ ] `Router is singleton in container`
- [ ] `route discovery runs after module loading`
- [ ] `route conflicts detected during boot (fail fast)`
- [ ] `integration works with modules from vendor, modules, and app directories`

## Acceptance Criteria
- All requirements have passing tests
- Clean integration with existing Application boot sequence
- Routes available immediately after boot
- No breaking changes to existing core API

## Files to Create
```
packages/routing/src/
  RoutingBootstrapper.php    # Integrates with Application boot
packages/routing/
  module.php                  # Optional: module-level bindings
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
