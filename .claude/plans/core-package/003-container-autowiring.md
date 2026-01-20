# Task 003: Container Interface and Autowiring

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the DI container with autowiring capability. The container reads constructor signatures via reflection and automatically resolves dependencies. This is the foundation of Marko's dependency injection system.

## Context
- Location: `packages/core/src/Container/`
- Implements PSR-11 ContainerInterface for interoperability
- Autowiring uses reflection to read constructor type hints
- Should handle both interface and concrete class resolution

## Requirements (Test Descriptions)
- [ ] `it resolves a class with no constructor dependencies`
- [ ] `it resolves a class with concrete class dependencies via autowiring`
- [ ] `it resolves nested dependencies recursively`
- [ ] `it throws BindingException when dependency cannot be resolved`
- [ ] `it returns same instance for shared bindings (singleton behavior)`
- [ ] `it creates new instance for non-shared bindings`
- [ ] `it implements PSR-11 ContainerInterface`
- [ ] `it returns true from has() for resolvable classes`
- [ ] `it returns false from has() for non-resolvable interfaces without binding`

## Acceptance Criteria
- All requirements have passing tests
- Container is stateless except for registered bindings and instances
- Reflection results can be cached for performance
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Container/
  ContainerInterface.php    # Extends PSR-11, adds Marko-specific methods
  Container.php             # Main implementation
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
