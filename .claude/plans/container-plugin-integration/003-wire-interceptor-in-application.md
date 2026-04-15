# Task 003: Wire PluginInterceptor into Application Bootstrap

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Update `Application::initialize()` to create a `PluginInterceptor` and pass it to the Container. This requires reordering the bootstrap sequence slightly: the PluginRegistry must be created before the Container, and the PluginInterceptor needs both the Container and PluginRegistry. Since PluginInterceptor needs the Container reference and Container needs the PluginInterceptor, we need to use a two-phase approach: create Container first, then create PluginInterceptor, then inject it into Container via a setter or by restructuring initialization order.

## Context
- Related files: `packages/core/src/Application.php`, `packages/core/src/Container/Container.php`
- Current bootstrap order in `initialize()`:
  1. Container is created with PreferenceRegistry
  2. `discoverPlugins()` creates PluginRegistry and populates it
- The problem: `PluginInterceptor` needs BOTH `ContainerInterface` and `PluginRegistry` in its constructor. Container must exist first.
- Solution: Use the setter approach (Task 001 added `setPluginInterceptor()`):
  1. Create `PluginRegistry` early (empty, mutable) — before `discoverPlugins()`
  2. Create Container as before
  3. Create `PluginInterceptor` with the Container and the empty PluginRegistry
  4. Call `$container->setPluginInterceptor($interceptor)`
  5. `discoverPlugins()` populates the SAME PluginRegistry instance (it's mutable, plugins are added via `register()` and checked at resolve-time)
- The existing `discoverPlugins()` method creates its own `new PluginRegistry()` — this must be changed to use the pre-created instance instead
- Register the PluginInterceptor and PluginRegistry as container instances for other services that may need them

## Requirements (Test Descriptions)
- [ ] `it creates PluginInterceptor and injects it into Container via setter during initialization`
- [ ] `it uses the same PluginRegistry instance for both discovery and interception`
- [ ] `it resolves objects with plugin interception after full initialization`
- [ ] `it creates PluginRegistry before plugin discovery and reuses it for PluginInterceptor`

## Acceptance Criteria
- All requirements have passing tests
- Existing ApplicationTest tests continue to pass
- Code follows code standards
