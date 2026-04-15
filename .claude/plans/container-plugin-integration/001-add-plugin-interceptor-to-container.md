# Task 001: Add PluginInterceptor Dependency to Container

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a `setPluginInterceptor()` setter method to Container, making plugin interception available during resolution without breaking existing code that creates containers without plugins. A setter is required (not a constructor parameter) because `PluginInterceptor` needs a `ContainerInterface` reference in its own constructor, creating a circular dependency that prevents both from being constructed simultaneously.

## Context
- Related files: `packages/core/src/Container/Container.php`
- The `PluginInterceptor` class already exists at `packages/core/src/Plugin/PluginInterceptor.php`
- Container is constructed in `Application::initialize()` — PluginInterceptor cannot be created until Container exists (PluginInterceptor's constructor requires `ContainerInterface`)
- `PluginInterceptor` is a `readonly class` — it takes `ContainerInterface` and `PluginRegistry` in its constructor
- Add a `private ?PluginInterceptor $pluginInterceptor = null` property (not constructor-promoted, not readonly since it is set after construction)
- Add a `public function setPluginInterceptor(PluginInterceptor $interceptor): void` method

## Requirements (Test Descriptions)
- [ ] `it accepts PluginInterceptor via setter method`
- [ ] `it resolves classes without PluginInterceptor when none is set`
- [ ] `it continues to work with PreferenceRegistry when PluginInterceptor is also set`

## Acceptance Criteria
- All requirements have passing tests
- Existing ContainerTest tests continue to pass unchanged
- Code follows code standards
