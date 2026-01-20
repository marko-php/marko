# Task 013: Preference Route Inheritance

**Status**: pending
**Depends on**: 002, 008
**Retry count**: 0

## Description
Extend route discovery to handle Preference inheritance rules. When a controller is replaced via #[Preference], the child class's routes must follow specific rules: inherit parent routes for non-overridden methods, use child's route for overridden methods with route attribute, remove route for #[DisableRoute], and ERROR for override without attribute.

## Context
- Location: `packages/routing/src/`
- Integrates with PreferenceRegistry from core
- Must detect when a controller class is a Preference replacement
- Must analyze parent class for inherited routes
- Must validate overridden methods have explicit route declaration

## Requirements (Test Descriptions)
- [ ] `it inherits parent route when method not overridden`
- [ ] `it uses child route when method overridden with route attribute`
- [ ] `it removes route when method overridden with DisableRoute`
- [ ] `it throws RouteException when method overridden without route attribute`
- [ ] `RouteException for ambiguous override includes class and method names`
- [ ] `RouteException suggests adding route attribute or DisableRoute`
- [ ] `it handles multi-level inheritance (grandparent routes)`
- [ ] `it correctly identifies Preference relationships via registry`
- [ ] `inherited routes use child controller class for dispatch`
- [ ] `it handles controller with no parent (no inheritance logic)`

## Acceptance Criteria
- All requirements have passing tests
- Clear error messages for ambiguous overrides
- Inheritance works with multiple levels
- Non-Preference controllers work unchanged

## Files to Create
```
packages/routing/src/
  PreferenceRouteResolver.php   # Handles inheritance logic
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
