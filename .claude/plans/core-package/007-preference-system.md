# Task 007: Preference Attribute and Resolution

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Create the Preference system that allows class → class replacement. Unlike bindings (interface → implementation), preferences replace one concrete class with another globally. Uses the `#[Preference]` attribute.

## Context
- Location: `packages/core/src/Attributes/` and `packages/core/src/Container/`
- Preferences are discovered via reflection on classes with #[Preference] attribute
- The replacement class typically extends the original
- Preferences are resolved before autowiring in the container

## Requirements (Test Descriptions)
- [ ] `it creates Preference attribute with replaces parameter`
- [ ] `it discovers classes with Preference attribute in module src directories`
- [ ] `it registers preference as class to class mapping`
- [ ] `it resolves original class request to preference class`
- [ ] `it chains preferences when A replaces B and C replaces A`
- [ ] `it throws BindingConflictException when multiple classes prefer same target`
- [ ] `it includes both preference classes in conflict error message`
- [ ] `it applies preference before autowiring resolution`
- [ ] `it allows preference class to extend and customize original`

## Acceptance Criteria
- All requirements have passing tests
- Preference attribute is simple and declarative
- Conflict detection is clear and helpful
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Attributes/
  Preference.php            # #[Preference(replaces: SomeClass::class)]
packages/core/src/Container/
  PreferenceRegistry.php    # Tracks class → class mappings
  PreferenceDiscovery.php   # Finds #[Preference] classes in modules
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
