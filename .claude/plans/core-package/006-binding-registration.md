# Task 006: Binding Registration from Modules

**Status**: pending
**Depends on**: 003, 005
**Retry count**: 0

## Description
Integrate module bindings with the container. As modules are loaded, their interface → implementation bindings are registered with the container, respecting the override priority (vendor < modules < app).

## Context
- Location: `packages/core/src/Container/`
- Override priority: vendor (lowest) → modules (middle) → app (highest)
- Conflicts without explicit override = LOUD ERROR
- Later directories can intentionally override earlier ones

## Requirements (Test Descriptions)
- [ ] `it registers bindings from module manifest to container`
- [ ] `it allows app module to override vendor module binding`
- [ ] `it allows modules directory to override vendor binding`
- [ ] `it allows app to override modules directory binding`
- [ ] `it throws BindingConflictException when same-priority modules bind same interface`
- [ ] `it includes both module names in BindingConflictException message`
- [ ] `it includes resolution suggestions in BindingConflictException`
- [ ] `it resolves interface to bound implementation via container`
- [ ] `it processes bindings in module load order`

## Acceptance Criteria
- All requirements have passing tests
- Conflict detection is deterministic
- Error messages clearly identify conflicting modules
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Container/
  BindingRegistry.php       # Tracks bindings with source module and priority
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
