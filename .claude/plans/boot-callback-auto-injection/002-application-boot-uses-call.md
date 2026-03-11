# Task 002: Use call() in Application Boot Loop

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `Application::boot()` to invoke module boot callbacks via `$this->container->call($module->boot)` instead of `($module->boot)($this->container)`. This enables boot callbacks to declare any registered dependency in their argument list.

## Context
- Related files: `packages/core/src/Application.php` (lines 104-121), `packages/core/tests/Unit/ApplicationTest.php`, `packages/errors-simple/module.php`, `packages/errors-advanced/module.php`, `packages/notification/module.php`
- **CRITICAL**: The container is NOT currently registered as an instance of `ContainerInterface`. You must add `$this->container->instance(ContainerInterface::class, $this->container)` right after the container is created (after line 104) so that boot callbacks requesting `ContainerInterface` can resolve it.
- Replace `($module->boot)($this->container)` with `$this->container->call($module->boot)`
- **CRITICAL**: All three existing module.php boot callbacks use untyped `function ($container)`. These MUST be updated to `function (ContainerInterface $container)` or they will throw BindingException (call() uses reflection and cannot resolve untyped params). Update:
  - `packages/errors-simple/module.php`: `function ($container)` -> `function (ContainerInterface $container)`
  - `packages/errors-advanced/module.php`: `function ($container)` -> `function (ContainerInterface $container)`
  - `packages/notification/module.php`: `function ($container)` -> `function (ContainerInterface $container)`
- **Existing tests must be updated**: Both boot callback tests in `ApplicationTest.php` (lines 1209 and 1261) use untyped `function ($container)` in their module.php heredocs. Update these to `function (\Marko\Core\Container\ContainerInterface $container)` (FQCN required since the heredoc content is a standalone PHP file)
- The `ModuleManifest::$boot` property type (`?Closure`) is already compatible

## Requirements (Test Descriptions)
- [ ] `it auto-injects dependencies into module boot callbacks`
- [ ] `it continues to work with boot callbacks that receive ContainerInterface`
- [ ] `it registers the container as an instance of ContainerInterface` (verify `$app->container->get(ContainerInterface::class)` returns the container)

## Acceptance Criteria
- All requirements have passing tests
- `$this->container->instance(ContainerInterface::class, $this->container)` added after container creation
- Application::boot() uses container->call() for boot callbacks
- All three existing module.php boot callbacks updated to use typed `ContainerInterface $container` parameter
- Both existing ApplicationTest boot callback tests updated to use typed parameters (FQCN in heredoc)
- Existing tests continue to pass
