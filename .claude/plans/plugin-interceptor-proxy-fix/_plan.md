# Plan: Plugin Interceptor Proxy Fix

## Created
2026-04-05

## Status
completed

## Objective
Replace the generic `PluginProxy` (which uses `__call()` and doesn't satisfy PHP's type system) with generated interceptor classes that properly implement target interfaces or extend target concrete classes, fixing TypeError on injection.

## Problem Statement

Two confirmed bugs:

1. **Type mismatch**: When a plugin targets an interface (e.g., `HasherInterface`), the container wraps the real implementation in a `PluginProxy` that doesn't implement the interface. PHP throws `TypeError` when the proxy is injected into a constructor that type-hints the interface.

2. **Preference path misses plugins**: When the container resolves an interface via `PreferenceRegistry` (not a closure binding), `$id` gets changed to the concrete class name before `createProxy()` is called. Plugins registered against the interface are never found because the registry is keyed by interface name.

Both bugs affect concrete class targets too — any constructor type-hinting a concrete class that has plugins will fail.

## Key Constraint: readonly classes

Many framework classes (`BcryptHasher`, `Argon2Hasher`, `LocalFilesystem`, `RedisPublisher`, etc.) are `readonly`. PHP prohibits extending readonly classes. This means "extend concrete class" is NOT universally applicable.

## Solution: Dual-Strategy Interceptor Generation

### Strategy 1: Interface Wrapper (for interface targets)
Generate a class via eval that `implements` the target interface, holds a reference to the real instance, and delegates all interface methods through the plugin chain. Works regardless of whether the concrete class is readonly.

### Strategy 2: Subclass Extension (for non-readonly concrete class targets)
Generate a class via eval that `extends` the concrete class, overrides only plugged methods with `parent::` calls through the plugin chain. The interceptor IS-A the concrete class.

### Strategy 3: Readonly concrete class targets
Throw a helpful `PluginException` explaining that readonly classes cannot be intercepted directly — target the interface instead.

### Core Logic in a Trait
The before→target→after plugin chain logic lives in a `PluginInterception` trait (real file, fully testable). Generated classes only contain thin method stubs that delegate to the trait.

### Container Changes
The container must pass `$originalId` (the interface/class originally requested) to the interceptor so it can look up plugins registered against interfaces even when preferences resolve to a concrete class.

## Code Standards Exception: Trait Usage

This plan uses a `PluginInterception` trait, which is a **deliberate exception** to code standard rule 14 ("No Traits"). The rationale: eval-generated interceptor classes must share the before/target/after chain logic. Traditional composition is not viable because (a) generated interface wrappers need the logic mixed in, and (b) the generated code must remain minimal stubs. The trait keeps the real logic in a single testable location while generated classes contain only thin delegating methods. This exception is scoped solely to the plugin interception system.

## Scope

### In Scope
- `PluginInterceptedInterface` with `getPluginTarget(): object` method (marker interface for intercepted objects)
- `PluginInterception` trait with before→target→after chain logic (implements `PluginInterceptedInterface`)
- `InterceptorClassGenerator` for eval-based class generation
- Rewrite `PluginInterceptor::createProxy()` to use the dual strategy
- Fix `Container::resolve()` to pass original ID for plugin lookup
- Add `PluginRegistry::getPluginsForClassOrInterfaces()` method
- New `PluginException` for readonly class targeting
- Remove `PluginProxy` class and update all references (including `packages/routing/src/Router.php`)
- Update all existing tests to work with new architecture (including `ContainerTest.php`)
- New tests for interface targets, readonly detection, preference path

### Out of Scope
- Changes to `PluginDiscovery` or `PluginDefinition` (these remain as-is)
- Changes to `Before`/`After`/`Plugin` attributes
- File-based code generation (explicitly rejected)
- Support for `final` classes (already banned by Marko conventions)

## Success Criteria
- [ ] Plugin targeting an interface works: container returns object that passes `instanceof` check
- [ ] Plugin targeting a non-readonly concrete class works via subclass extension
- [ ] Plugin targeting a readonly concrete class throws helpful error
- [ ] Plugins found when interface is resolved via preferences (not just bindings)
- [ ] All existing plugin behaviors preserved (before passthrough, short-circuit, arg modification, after chaining)
- [ ] `getPluginTarget()` still available for reflection-based code
- [ ] All tests passing with min 80% coverage
- [ ] Code follows project standards (phpcs, php-cs-fixer)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | PluginInterceptedInterface + PluginInterception trait | - | completed |
| 002 | InterceptorClassGenerator | - | completed |
| 003 | PluginRegistry interface-aware lookup | - | completed |
| 004 | PluginException for readonly + ambiguous interface targets | - | completed |
| 005 | Rewrite PluginInterceptor | 001, 002, 003, 004 | completed |
| 006 | Container resolve fix | 005 | completed |
| 007 | Remove PluginProxy, update imports (Router.php, ContainerTest.php) | 005, 006 | completed |
| 008 | Integration tests | 007 | completed |

## Architecture Notes

### PluginInterceptedInterface
```php
// Real file — packages/core/src/Plugin/PluginInterceptedInterface.php
interface PluginInterceptedInterface {
    public function getPluginTarget(): object;
}
```

All generated interceptor classes implement this interface (provided by the trait). This allows code like `Router.php` to detect intercepted objects without referencing a specific class.

### Generated Class Shape (Interface Wrapper)
```php
// Generated via eval — NOT a real file
class Marko_Interceptor_HasherInterface_abc123 implements HasherInterface, PluginInterceptedInterface {
    use PluginInterception;

    public function __construct(
        object $__target,
        string $__targetClass,
        ContainerInterface $__container,
        PluginRegistry $__registry,
    ) {
        $this->initInterception($__target, $__targetClass, $__container, $__registry);
    }

    public function hash(string $value): string {
        return $this->interceptCall('hash', [$value]);
    }
    public function verify(string $value, string $hash): bool {
        return $this->interceptCall('verify', [$value, $hash]);
    }
    // ... all interface methods
}
```

### Generated Class Shape (Concrete Subclass)
```php
// Generated via eval — NOT a real file
// NOTE: Generated classes must NOT be readonly (trait properties are mutable)
class Marko_Interceptor_SomeService_abc123 extends SomeService implements PluginInterceptedInterface {
    use PluginInterception;

    // No constructor — inherits parent's
    // initInterception called by the interceptor after construction

    public function pluggedMethod(string $arg): string {
        return $this->interceptParentCall('pluggedMethod', [$arg], parent::pluggedMethod(...));
    }
    // Only overrides methods that have plugins
}
```

### Plugin Lookup Flow
1. Container resolves `HasherInterface` → tracks `$originalId = 'HasherInterface'`
2. Preference/binding resolves to `BcryptHasher`
3. Instance created
4. `createProxy($originalId, $resolvedId, $instance)` called
5. Interceptor checks registry for plugins on `HasherInterface` OR `BcryptHasher`
6. Finds plugins → determines strategy (interface wrapper since `HasherInterface` is interface)
7. Generates interceptor class implementing `HasherInterface`
8. Returns interceptor wrapping the real `BcryptHasher` instance

## Risks & Mitigations
- **eval security**: Generated code is deterministic from reflection data — no user input involved. Minimal surface area (method signatures only). Mitigated by keeping generated code trivial.
- **Method signature edge cases**: Union types, intersection types, nullable, variadic, defaults. Mitigated by thorough test coverage of signature generation.
- **Debugging**: Stack traces show eval'd code. Mitigated by keeping generated methods as one-line delegations to the trait.
- **readonly concrete targets**: Cannot extend. Mitigated by clear error message directing developer to target the interface instead.
- **Subclass constructor args**: For concrete subclass strategy, the interceptor inherits the parent constructor. The container creates the interceptor class directly with the same args. Requires container to know the interceptor class name before instantiation.
- **Multiple interfaces with plugins on same class**: Throw `PluginException::ambiguousInterfacePlugins()` — loud error over silent "first match wins" behavior.

## Naming Convention

Trait methods and properties do NOT use double-underscore prefixes (reserved for PHP magic methods):
- `initInterception()` (not `__initInterception`)
- `interceptCall()` (not `__intercept`)
- `interceptParentCall()` (not `__interceptParent`)
- `pluginTarget` property (not `__target`)
- `pluginTargetClass` property (not `__targetClass`)
- `pluginContainer` property (not `__container`)
- `pluginRegistry` property (not `__registry`)
