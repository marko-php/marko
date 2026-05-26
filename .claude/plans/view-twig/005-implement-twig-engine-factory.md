# Task 005: Implement TwigEngineFactory

**Status**: pending
**Depends on**: 004
**Retry count**: 0

## Description
Create `TwigEngineFactory` that builds a configured `Twig\Environment` instance from `TwigViewConfig`. Mirrors the shape of `LatteEngineFactory` but uses Twig's API. The loader is set separately when `TwigView` is constructed (so the factory doesn't need the resolver).

## Context
- Related files:
  - `packages/view-twig/src/TwigEngineFactory.php` (new)
  - `packages/view-twig/tests/TwigEngineFactoryTest.php` (new)
  - `packages/view-latte/src/LatteEngineFactory.php` (reference pattern)
- Twig\Environment construction:
  ```php
  $loader = new \Twig\Loader\ArrayLoader();  // placeholder, replaced by TwigView with ModuleLoader
  $environment = new \Twig\Environment($loader, [
      'cache' => $config->cacheDirectory(),
      'auto_reload' => $config->autoRefresh(),
      'strict_variables' => $config->strictVariables(),
      'autoescape' => $config->autoescape(),
      'debug' => $config->debug(),
      'charset' => $config->charset(),
  ]);
  ```
- The factory creates and returns the `Environment`; `TwigView`'s constructor sets the real loader
- Readonly class, constructor property promotion

## Requirements (Test Descriptions)
- [ ] `it returns a Twig Environment instance`
- [ ] `it sets the cache directory from config`
- [ ] `it sets auto_reload from auto_refresh config`
- [ ] `it sets strict_variables from config`
- [ ] `it sets autoescape from config`
- [ ] `it sets debug from config`
- [ ] `it sets charset from config`

## Acceptance Criteria
- `TwigEngineFactory` is a readonly class with constructor property promotion
- Constructor takes `TwigViewConfig` (autowired by the container — `TwigViewConfig` itself autowires from `ConfigRepositoryInterface`)
- `create(): \Twig\Environment` method returns a fresh environment per call
- All Environment options sourced from `TwigViewConfig` — no inline defaults
- Cache directory passed to Twig's `cache` option must be a non-empty string (Twig accepts `false` to disable caching, but our `TwigViewConfig::cacheDirectory()` returns a required string per the loud-errors principle)
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
