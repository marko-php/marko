# Task 008: Ship view-twig config/view.php with defaults

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create `packages/view-twig/config/view.php` shipping all default values that `TwigViewConfig` reads. This is the single source of truth for Twig driver defaults; per Marko's "no silent fallbacks" principle, every value `TwigViewConfig` accesses must be present here.

## Context
- Related files:
  - `packages/view-twig/config/view.php` (new)
  - `packages/view-twig/tests/ViewConfigDefaultsTest.php` (new — verifies all keys are present)
- Required keys and recommended defaults:
  - `extension: '.twig'` — Twig's standard extension
  - `strict_variables: true` — aligns with Marko's "loud errors" premise (undefined variables throw)
  - `autoescape: 'html'` — safe default for web apps
  - `debug: false` — production-safe default
  - `charset: 'UTF-8'`
- **Do NOT redeclare `cache_directory` or `auto_refresh`** — these are shared keys that live in `marko/view`'s `config/view.php` (the interface package ships them once for all drivers). Marko's `ConfigDiscovery` merges all `config/view.php` files from installed modules into a single `view.*` namespace, so values from `marko/view` and `marko/view-twig` combine cleanly.
- The file returns a flat array (no nested `view` key — Marko's config loader prefixes by file name)

## Requirements (Test Descriptions)
- [ ] `it ships a config/view.php file`
- [ ] `it defines extension as .twig`
- [ ] `it defines strict_variables as true`
- [ ] `it defines autoescape as html`
- [ ] `it defines debug as false`
- [ ] `it defines charset as UTF-8`
- [ ] `it does not redeclare cache_directory (inherited from marko/view shared config)`
- [ ] `it does not redeclare auto_refresh (inherited from marko/view shared config)`
- [ ] `it returns a flat array (not nested under a view key)`

## Acceptance Criteria
- `packages/view-twig/config/view.php` exists
- All five Twig-specific keys are present with the specified defaults
- The file does NOT contain `cache_directory` or `auto_refresh` (those come from `marko/view`'s shipped config)
- File uses `declare(strict_types=1)` and returns an array
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
