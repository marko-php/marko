# Task 006: DisableRoute Attribute

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the DisableRoute attribute used to explicitly remove an inherited route when overriding a controller method via Preference. This makes the intent clear when you want to remove a route rather than replace it.

## Context
- Location: `packages/routing/src/Attributes/`
- Used when extending a controller via #[Preference]
- Explicitly signals "this route should not exist"
- Without this, overriding a routed method without any route attribute is an error
- Simple marker attribute with no parameters

## Requirements (Test Descriptions)
- [ ] `DisableRoute attribute has no parameters`
- [ ] `DisableRoute attribute targets methods only`
- [ ] `DisableRoute attribute is readonly`
- [ ] `DisableRoute can be instantiated without arguments`

## Acceptance Criteria
- All requirements have passing tests
- Attribute is simple marker (no configuration needed)
- Clear PHPDoc explaining purpose and usage

## Files to Create
```
packages/routing/src/Attributes/
  DisableRoute.php    # #[DisableRoute]
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
