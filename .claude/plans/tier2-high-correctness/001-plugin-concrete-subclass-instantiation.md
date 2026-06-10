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
- [ ] `it intercepts a #[Before] plugin method on a concrete class whose constructor
      has a mandatory promoted dependency without throwing ArgumentCountError`
- [ ] `it intercepts an #[After] plugin method on a constructor-DI concrete class and
      returns the plugin-modified result`
- [ ] `it runs a PLUGGED method that reads a constructor-injected property and returns the
      real injected value (parent:: call sees the copied state, not an uninitialized property)`
- [ ] `it delegates non-plugged public methods on a constructor-DI concrete target,
      reading the real constructor-injected state without an uninitialized-property Error`
- [ ] `it copies private and protected properties from the target onto the interceptor
      (state from base classes in the hierarchy is preserved)`
- [ ] `it does not invoke the target's parent constructor when building the concrete
      subclass interceptor (instantiates via newInstanceWithoutConstructor)`
- [ ] `it leaves the interface-wrapper strategy unchanged for interface targets with plugins`
- [ ] `it still throws the existing loud PluginException for readonly concrete targets`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
