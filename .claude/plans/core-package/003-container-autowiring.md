# Task 003: Container Interface and Autowiring

**Status**: complete
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
- [x] `it resolves a class with no constructor dependencies`
- [x] `it resolves a class with concrete class dependencies via autowiring`
- [x] `it resolves nested dependencies recursively`
- [x] `it throws BindingException when dependency cannot be resolved`
- [x] `it returns same instance for shared bindings (singleton behavior)`
- [x] `it creates new instance for non-shared bindings`
- [x] `it implements PSR-11 ContainerInterface`
- [x] `it returns true from has() for resolvable classes`
- [x] `it returns false from has() for non-resolvable interfaces without binding`

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

### Files Created
- `packages/core/src/Container/ContainerInterface.php` - Extends PSR-11, adds `singleton()` method
- `packages/core/src/Container/Container.php` - Main DI container implementation

### Key Design Decisions
1. **Autowiring via Reflection**: Uses `ReflectionClass` to inspect constructor parameters and automatically resolve dependencies
2. **Singleton Pattern**: Uses `singleton()` method to register classes as shared instances, stored in `$instances` array
3. **Non-shared by Default**: Classes create new instances each time unless explicitly registered as singleton
4. **Interface Detection**: Throws `BindingException` for unbound interfaces (cannot instantiate interfaces directly)
5. **PSR-11 Compliance**: Implements `Psr\Container\ContainerInterface` through extended `ContainerInterface`

### Test Coverage
- 9 tests covering all requirements
- Tests use inline fixture classes for isolation
