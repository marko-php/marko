# Task 001: PluginInterception Trait

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the `PluginInterceptedInterface` and `PluginInterception` trait that contains the core before→target→after plugin chain logic. This trait will be `use`d by generated interceptor classes (both interface wrappers and concrete subclasses). It replaces the logic currently in `PluginProxy::__call()`.

**Code standards exception**: This trait is a deliberate exception to rule 14 ("No Traits"). See `_plan.md` for rationale. The trait is the only viable mechanism for sharing logic with eval-generated classes while keeping generated code minimal.

## Context
- Related files:
  - `packages/core/src/Plugin/PluginProxy.php` — current logic to extract from
  - `packages/core/src/Plugin/PluginRegistry.php` — used for plugin lookups
  - `packages/core/src/Plugin/PluginArgumentCountException.php` — thrown on arg count mismatch
  - `packages/core/src/Container/ContainerInterface.php` — for resolving plugin instances
- New files:
  - `packages/core/src/Plugin/PluginInterceptedInterface.php` — marker interface with `getPluginTarget(): object`
  - `packages/core/src/Plugin/PluginInterception.php` — trait implementing the interface
- Patterns to follow: strict types, constructor property promotion
- **Important**: The trait properties (`$pluginTarget`, `$pluginTargetClass`, etc.) must be mutable (not readonly) because they are set via `initInterception()` after object construction. Generated classes that use this trait must NOT be `readonly class`.
- **Naming**: No double-underscore prefixes — those are reserved for PHP magic methods. Use `initInterception`, `interceptCall`, `interceptParentCall`, `pluginTarget`, `pluginTargetClass`, `pluginContainer`, `pluginRegistry`.

## Requirements (Test Descriptions)

Tests go in `packages/core/tests/Unit/Plugin/PluginInterceptionTest.php`.

For testing, create a concrete test class that `use`s the trait and exposes the `interceptCall` method publicly. This lets us test the trait in isolation.

- [ ] `it initializes interception state via initInterception`
- [ ] `it executes before plugins in sort order then calls target method via interceptCall`
- [ ] `it passes method arguments to before plugins`
- [ ] `it short-circuits when before plugin returns non-null non-array value`
- [ ] `it skips remaining before plugins and target method on short-circuit`
- [ ] `it passes through to target method when before plugin returns null`
- [ ] `it modifies arguments when before plugin returns an array`
- [ ] `it throws PluginArgumentCountException when before plugin returns array with wrong count`
- [ ] `it chains argument modifications through multiple before plugins`
- [ ] `it executes after plugins in sort order after target method`
- [ ] `it passes result and original arguments to after plugins`
- [ ] `it chains modified results through multiple after plugins`
- [ ] `it passes modified arguments from before plugins to after plugins`
- [ ] `it executes complete flow of before plugins then target then after plugins`
- [ ] `it returns target instance via getPluginTarget`
- [ ] `it executes parent call via interceptParentCall for subclass strategy`

## Acceptance Criteria
- All requirements have passing tests
- `PluginInterceptedInterface` created with `getPluginTarget(): object` method
- Trait implements `PluginInterceptedInterface`
- Trait contains two public interception methods: `interceptCall` (for wrapper strategy — calls `$this->pluginTarget->$method()`) and `interceptParentCall` (for subclass strategy — receives a `Closure` for `parent::method(...)`)
- `initInterception()` method stores target, targetClass, container, and registry
- `getPluginTarget()` returns the wrapped target instance
- Trait properties are NOT readonly (they are set via `initInterception` post-construction)
- No decrease in test coverage
- Code follows code standards (with documented trait exception)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
