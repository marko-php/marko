# Task 009: Wire view-twig module.php bindings

**Status**: pending
**Depends on**: 007, 008
**Retry count**: 0

## Description
Create `packages/view-twig/module.php` registering DI bindings so that `ViewInterface` resolves to a fully-wired `TwigView`. Mirrors the closure-based binding pattern in `view-latte/module.php`.

## Context
- Related files:
  - `packages/view-twig/module.php` (new)
  - `packages/view-twig/tests/ModuleTest.php` (new — verifies bindings resolve correctly)
  - `packages/view-latte/module.php` (reference pattern)
- Required binding:
  ```php
  return [
      'bindings' => [
          ViewInterface::class => function (ContainerInterface $container): ViewInterface {
              $engine = $container->get(TwigEngineFactory::class)->create();
              $resolver = $container->get(TemplateResolverInterface::class);
              return new TwigView($engine, $resolver);
          },
      ],
  ];
  ```
- The `TwigEngineFactory` and `TwigViewConfig` resolve via autowiring — `TwigEngineFactory` takes a `TwigViewConfig`, which takes a `ConfigRepositoryInterface` (a singleton already registered by `marko/config`)
- `ModuleTest.php` should mirror `packages/view-latte/tests/ModuleTest.php`: assert `module.php` exists, returns an array with a `bindings` key, the binding for `ViewInterface::class` is a `Closure`, and calling the closure with a mock container that returns mocked `TwigEngineFactory` and `TemplateResolverInterface` produces a `TwigView` instance

## Requirements (Test Descriptions)
- [ ] `it registers a ViewInterface binding`
- [ ] `it resolves ViewInterface to a TwigView instance`
- [ ] `it injects a configured Twig Environment into TwigView`
- [ ] `it injects the shared TemplateResolverInterface into TwigView`

## Acceptance Criteria
- `module.php` exists with a `bindings` array
- `ViewInterface::class` binding is a closure that returns a wired `TwigView`
- File uses `declare(strict_types=1)`
- All referenced classes have proper `use` imports
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
