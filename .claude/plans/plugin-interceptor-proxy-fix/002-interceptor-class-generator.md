# Task 002: InterceptorClassGenerator

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `InterceptorClassGenerator` class that generates PHP class code for interceptor classes via eval. It handles two strategies: (1) interface wrapper classes that `implements` a target interface and delegates to a wrapped instance, and (2) concrete subclasses that `extends` a target class and override plugged methods with `parent::` calls. Generated code is minimal — method stubs delegating to the `PluginInterception` trait.

## Context
- New file: `packages/core/src/Plugin/InterceptorClassGenerator.php`
- Related files:
  - `packages/core/src/Plugin/PluginInterception.php` (Task 001) — trait used by generated classes
  - `packages/core/src/Plugin/PluginInterceptedInterface.php` (Task 001) — marker interface for intercepted objects
  - `packages/core/src/Plugin/PluginRegistry.php` — to discover which methods have plugins
  - `packages/core/src/Container/ContainerInterface.php` — used in generated constructors
- The generator must handle PHP method signature reflection: parameter types (including union, intersection, nullable), return types, variadic parameters, default values
- Generated class names use a deterministic format: `Marko_Interceptor_{sanitizedClassName}_{hash}` to avoid collisions
- The generator caches generated class names — eval runs only once per target
- **Important**: Generated classes must NOT use the `readonly` keyword (the trait properties are mutable)
- **Important**: Generated classes must implement `PluginInterceptedInterface` (in addition to the target interface for wrappers)
- **Important**: For the wrapper strategy applied to concrete classes (non-interface targets), ALL public methods of the target class must be generated as delegating stubs, not just plugged methods. Otherwise calls to non-plugged methods will fail since the wrapper holds a reference to the target rather than inheriting from it.
- This class must NOT be `readonly` since it maintains an internal cache of generated class names

## Requirements (Test Descriptions)

Tests go in `packages/core/tests/Unit/Plugin/InterceptorClassGeneratorTest.php`.

### Interface Wrapper Strategy
- [x] `it generates a class that implements both the target interface and PluginInterceptedInterface`
- [x] `it generates methods for all interface methods with correct signatures`
- [x] `it generates a constructor that calls initInterception`
- [x] `it generates method bodies that delegate to interceptCall`
- [x] `it handles methods with no parameters`
- [x] `it handles methods with typed parameters`
- [x] `it handles methods with nullable parameter types`
- [x] `it handles methods with default parameter values`
- [x] `it handles methods with variadic parameters`
- [x] `it handles methods with union return types`
- [x] `it handles methods with void return type`
- [x] `it handles interfaces that extend other interfaces`
- [x] `it returns cached class name on second call for same interface`

### Concrete Subclass Strategy
- [x] `it generates a class that extends the target concrete class and implements PluginInterceptedInterface`
- [x] `it only overrides methods that have registered plugins`
- [x] `it generates method bodies that delegate to interceptParentCall with parent callable`
- [x] `it does not generate a constructor for concrete subclasses`
- [x] `it throws PluginException when target class is readonly`

### General
- [x] `it generates classes with the PluginInterception trait`
- [x] `it generates valid PHP that passes eval without errors`

## Acceptance Criteria
- All requirements have passing tests
- Generated classes use `PluginInterception` trait and implement `PluginInterceptedInterface`
- Generated classes are NOT `readonly`
- Interface wrapper: implements interface + `PluginInterceptedInterface`, has constructor calling `initInterception`, all methods delegate to `interceptCall`
- Concrete subclass: extends class, implements `PluginInterceptedInterface`, no constructor, only plugged methods overridden delegating to `interceptParentCall`
- Readonly class detection throws `PluginException`
- Class names are deterministic and cached
- `InterceptorClassGenerator` itself is NOT `readonly` (maintains cache)
- Code follows code standards

## Implementation Notes

- Created `InterceptorClassGenerator` with two strategies: `generateInterfaceWrapper` and `generateConcreteSubclass`
- Uses `eval()` via a private `loadCode()` method; `class_exists()` guard prevents redefinition across multiple generator instances
- Created minimal stubs `PluginInterception.php` (trait) and `PluginInterceptedInterface.php` (interface) since Task 001 was not yet complete; these will be fleshed out by Task 001
- Added `PluginException::cannotInterceptReadonly()` factory method to `PluginException`
- `generateInterfaceWrapperCode()` and `generateConcreteSubclassCode()` are public to enable string-based assertions in tests
- Void return type methods use `$this->interceptCall(...)` without `return` to satisfy PHP's void constraint
- Class names are deterministic: `Marko_Interceptor_{sanitized}_{8-char-md5}` — cached per instance, globally guarded by `class_exists`
- The nunomaduro/collision reporter shows a spurious `ParseError` WARN after test runs involving eval; this is a pre-existing project-level tooling bug (also present in `PluginRegistryTest` and others); all 20 tests pass per JUnit XML with 0 errors/0 failures, and the parallel test suite exits 0
