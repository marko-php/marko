# Task 012: Demo Application

**Status**: pending
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
- [ ] `demo has composer.json with path repository pointing to packages/core`
- [ ] `demo has public/index.php entry point that bootstraps application`
- [ ] `demo has app/greeter module with module.php manifest`
- [ ] `demo greeter module defines GreeterInterface and DefaultGreeter binding`
- [ ] `demo resolves GreeterInterface to DefaultGreeter via container`
- [ ] `demo has app/custom module with preference for DefaultGreeter`
- [ ] `demo preference CustomGreeter replaces DefaultGreeter`
- [ ] `demo has plugin that modifies greeter output with Before hook`
- [ ] `demo has plugin that modifies greeter output with After hook`
- [ ] `demo dispatches GreetingCreated event after greeting`
- [ ] `demo has observer that logs when GreetingCreated fires`
- [ ] `demo index.php outputs result demonstrating all features working`

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
(Left blank - filled in by programmer during implementation)
