# Task 004: Implement TwigViewConfig with typed accessors

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create `TwigViewConfig` — a readonly class with typed accessors for all Twig-specific settings plus the shared view settings the engine factory needs. Lives in the `marko/view-twig` package because these settings are Twig-specific and shouldn't pollute the shared `ViewConfig` in `marko/view`.

## Context
- Related files:
  - `packages/view-twig/src/TwigViewConfig.php` (new)
  - `packages/view-twig/tests/TwigViewConfigTest.php` (new)
  - `packages/view/src/ViewConfig.php` (reference pattern — readonly, takes `ConfigRepositoryInterface`)
- All accessors must use the strongly-typed `ConfigRepositoryInterface` getters (`getString`, `getBool`) which throw `ConfigNotFoundException` on missing keys — no silent fallbacks
- Accessors needed:
  - `cacheDirectory(): string` — reads `view.cache_directory`
  - `extension(): string` — reads `view.extension`
  - `autoRefresh(): bool` — reads `view.auto_refresh`
  - `strictVariables(): bool` — reads `view.strict_variables`
  - `autoescape(): string` — reads `view.autoescape`
  - `debug(): bool` — reads `view.debug`
  - `charset(): string` — reads `view.charset`

## Requirements (Test Descriptions)
- [ ] `it returns cache_directory from config`
- [ ] `it returns extension from config`
- [ ] `it returns auto_refresh as bool from config`
- [ ] `it returns strict_variables as bool from config`
- [ ] `it returns autoescape from config`
- [ ] `it returns debug as bool from config`
- [ ] `it returns charset from config`
- [ ] `it throws ConfigNotFoundException when a required key is missing`

## Acceptance Criteria
- `TwigViewConfig` is a readonly class with constructor property promotion
- Constructor takes `ConfigRepositoryInterface` as its only parameter
- All accessors typed (return types declared)
- All values resolved via `ConfigRepositoryInterface` — no PHP-level defaults
- Code follows code standards (strict_types, no magic methods, no traits)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
