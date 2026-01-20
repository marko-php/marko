# Task 012: Demo Application

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Create a working demo application that exercises all core features: module loading, DI with autowiring, bindings, preferences, plugins, and events. This validates the framework works end-to-end.

## Context
- Location: `demo/`
- Uses the three-directory structure (vendor/, modules/, app/)
- Uses Composer path repository to load marko/core from ../packages/core
- Should demonstrate each core feature clearly
- Acts as both validation and documentation

## Requirements (Test Descriptions)
- [x] `demo has composer.json with path repository pointing to packages/core`
- [x] `demo has public/index.php entry point that bootstraps application`
- [x] `demo has app/greeter module with module.php manifest`
- [x] `demo greeter module defines GreeterInterface and DefaultGreeter binding`
- [x] `demo resolves GreeterInterface to DefaultGreeter via container`
- [x] `demo has app/custom module with preference for DefaultGreeter`
- [x] `demo preference CustomGreeter replaces DefaultGreeter`
- [x] `demo has plugin that modifies greeter output with Before hook`
- [x] `demo has plugin that modifies greeter output with After hook`
- [x] `demo dispatches GreetingCreated event after greeting`
- [x] `demo has observer that logs when GreetingCreated fires`
- [x] `demo index.php outputs result demonstrating all features working`

## Acceptance Criteria
- All requirements have passing tests
- Demo boots without errors
- Demo output clearly shows each feature working
- Demo serves as example for framework users

## Files to Create
```
demo/
  composer.json             # Path repository to ../packages/core
  public/
    index.php               # Web entry point
  app/
    greeter/
      module.php
      src/
        Contracts/
          GreeterInterface.php
        DefaultGreeter.php
        Events/
          GreetingCreated.php
    custom/
      module.php
      src/
        CustomGreeter.php   # #[Preference(replaces: DefaultGreeter::class)]
        Plugins/
          GreeterPlugin.php # #[Plugin(DefaultGreeter::class)]
        Observers/
          GreetingLogger.php # #[Observer(event: GreetingCreated::class)]
  vendor/                   # Empty initially, Composer will populate
  modules/                  # Empty initially
```

## Implementation Notes
Demo application created successfully. Key implementation details:

1. **composer.json**: Uses path repository to symlink packages/core, with PSR-4 autoload for Demo\Greeter\ and Demo\Custom\ namespaces.

2. **app/greeter module**: Contains GreeterInterface, DefaultGreeter, and GreetingCreated event. Binding registered in module.php.

3. **app/custom module**: Contains CustomGreeter preference (#[Preference]), GreeterPlugin with #[Before] and #[After] hooks, and GreetingLogger observer (#[Observer]).

4. **public/index.php**: Bootstraps Application, demonstrates all features with clear output sections for each.

5. **Bug fix**: Application.php needed to check for #[Plugin] attribute before calling parsePluginClass() because files containing the string "#[Plugin" were being discovered (including PluginDiscovery.php itself).

Output shows all features working:
- 4 modules discovered (psr/container, marko/core, demo/greeter, demo/custom)
- Interface bindings resolved
- Preferences replaced DefaultGreeter with CustomGreeter
- Plugins enhanced output with "[plugin enhanced]"
- Events dispatched and logged by observer
