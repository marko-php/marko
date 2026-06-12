# Task 001: F1 — Concrete-subclass interceptor instantiation for constructor-DI targets

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Plugins applied to a concrete class whose constructor has mandatory promoted
dependencies currently crash: `PluginInterceptor` does `new $className()` with zero
args and the generated subclass extends the DI target, throwing `ArgumentCountError`.
Make the concrete-subclass path instantiate without invoking the parent constructor,
then give the interceptor instance the SAME state as the already-constructed target so
plugged AND non-plugged calls run against real constructor-injected state. This
supersedes only the concrete-subclass slice that `plugin-interceptor-proxy-fix`
task 005 explicitly deferred ("for now, prefer interface wrapper when possible").

## CRITICAL design correction (do not skip)
The concrete-subclass generated methods call `parent::$method(...)` via the trait's
`interceptParentCall()` — NOT `$target->$method(...)`. Non-plugged public methods are
NOT overridden at all, so they dispatch to the parent body via inheritance. BOTH paths
execute the parent body against `$this` (the interceptor instance), never against
`$target`. Therefore `newInstanceWithoutConstructor()` ALONE is insufficient: the
interceptor's own promoted/typed properties would be uninitialized, and the first
plugged-or-non-plugged method that touches a constructor-injected property throws
`Error: typed property must not be accessed before initialization`.

The fix must instantiate via `newInstanceWithoutConstructor()` AND copy the target's
property values onto the interceptor instance (reflection-copy every property from
`$target`, including private/protected, walking the class hierarchy) BEFORE
`initInterception()`. After the copy, the interceptor is a stateful stand-in for
`$target`: `parent::$method` and inherited non-plugged methods all see the real state.
(The interface-wrapper path is different — it delegates to `$target` directly — and
must remain unchanged.) Do NOT emit a zero-arg constructor on the generated subclass
(it would shadow the parent and is unnecessary).

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Plugin/PluginInterceptor.php`
    (the concrete-subclass branch near the end of the method: `generateConcreteSubclass(...)`
    then `$instance = new $className();` then `initInterception($target, $resolvedId, ...)`)
  - `/Users/markshust/Sites/marko/packages/core/src/Plugin/InterceptorClassGenerator.php`
    (`generateConcreteSubclassCode()` emits `class X extends \Target implements
    PluginInterceptedInterface { use PluginInterception; <methods> }` with no constructor)
  - `/Users/markshust/Sites/marko/packages/core/src/Plugin/PluginInterception.php`
    (the `initInterception(...)` + delegation trait — DO NOT change its public behaviour)
  - `/Users/markshust/Sites/marko/packages/core/src/Plugin/PluginInterceptedInterface.php`
- Patterns to follow:
  - The interface-wrapper branch in the same file already does `new $className()` +
    `initInterception($target, $interfaceName, ...)` and works because the wrapper
    holds and delegates to `$target`. Mirror that delegation for concrete subclasses
    by instantiating via `newInstanceWithoutConstructor()` so the parent ctor is never
    called with zero args.
  - `plugin-interceptor-proxy-fix/_plan.md` "Generated Class Shape (Concrete Subclass)"
    and task 005 "Subclass Instantiation" for the intended (deferred) shape.
  - Anonymous-class stub guidelines in `.claude/testing.md` (skipping parent ctor).

## Requirements (Test Descriptions)
- [x] `it intercepts a #[Before] plugin method on a concrete class whose constructor
      has a mandatory promoted dependency without throwing ArgumentCountError`
- [x] `it intercepts an #[After] plugin method on a constructor-DI concrete class and
      returns the plugin-modified result`
- [x] `it runs a PLUGGED method that reads a constructor-injected property and returns the
      real injected value (parent:: call sees the copied state, not an uninitialized property)`
- [x] `it delegates non-plugged public methods on a constructor-DI concrete target,
      reading the real constructor-injected state without an uninitialized-property Error`
- [x] `it copies private and protected properties from the target onto the interceptor
      (state from base classes in the hierarchy is preserved)`
- [x] `it does not invoke the target's parent constructor when building the concrete
      subclass interceptor (instantiates via newInstanceWithoutConstructor)`
- [x] `it leaves the interface-wrapper strategy unchanged for interface targets with plugins`
- [x] `it still throws the existing loud PluginException for readonly concrete targets`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

### Changes made

**`packages/core/src/Plugin/PluginInterceptor.php`** — The concrete-subclass path:
- Replaced `new $className()` with `(new ReflectionClass($className))->newInstanceWithoutConstructor()`
- Added `$this->copyProperties($target, $instance)` call before `initInterception()`
- Added `copyProperties(object $source, object $destination): void` private method
  that walks the source class hierarchy, processes only properties declared in each
  class (to avoid double-setting inherited readonly properties), checks initialization,
  and copies values via `ReflectionProperty::setValue()` (accessible in PHP 8.1+
  without `setAccessible()` which is deprecated in PHP 8.1+)

**`packages/core/tests/Unit/Plugin/PluginConcreteSubclassDITest.php`** — New test file
with 8 tests covering all requirements. Key insight: `ReflectionClass::getProperties()`
returns inherited properties too, so the filter `$property->getDeclaringClass()->getName() === $class->getName()`
prevents double-setting readonly inherited properties when walking the hierarchy.
