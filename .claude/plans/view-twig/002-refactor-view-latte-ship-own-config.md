# Task 002: Refactor marko/view-latte — ship own config + LatteViewConfig + conflict declaration

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
After task 001 removes the `extension` default (and the `strictTypes()` accessor) from `marko/view`, view-latte must:

1. **Ship its own `config/view.php`** so existing apps continue to work with no behavior change. This file contributes Latte-specific keys to the `view.*` config namespace.
2. **Introduce a new `LatteViewConfig` readonly class** that mirrors the pattern of `TwigViewConfig` (task 004). This class hosts the Latte-only `strictTypes()` accessor that was removed from shared `ViewConfig` in task 001. This is the architectural symmetry the plan requires: every driver hosts its driver-specific config accessors in its own package.
3. **Refactor `LatteEngineFactory`** to accept `LatteViewConfig` (for `strictTypes()`) alongside the shared `ViewConfig` (for `cacheDirectory()` and `autoRefresh()`). Update the constructor and `create()` method accordingly.
4. **Add a Composer `conflict` declaration** so view-latte and view-twig cannot be installed simultaneously — enforcing mutual exclusion at install time rather than runtime.

## Context
- Related files:
  - `packages/view-latte/config/view.php` (new file — does not currently exist; view-latte has no `config/` directory)
  - `packages/view-latte/src/LatteViewConfig.php` (new — readonly class with `strictTypes(): bool` accessor)
  - `packages/view-latte/src/LatteEngineFactory.php` (modify constructor to take `LatteViewConfig`; replace `$viewConfig->strictTypes()` with `$latteViewConfig->strictTypes()`)
  - `packages/view-latte/composer.json` (add `conflict` block)
  - `packages/view-latte/tests/LatteEngineFactoryTest.php` (update mocks: add LatteViewConfig mock alongside ViewConfig mock)
  - `packages/view-latte/tests/LatteViewConfigTest.php` (new — verifies the typed accessor)
  - `packages/view-latte/tests/ViewConfigDefaultsTest.php` (new — verifies the shipped config/view.php has expected keys)
- The new `config/view.php` keys:
  - `extension => '.latte'`
  - `strict_types => true`
  - (Do NOT duplicate `cache_directory` or `auto_refresh` here — those live in `marko/view`'s shared config since they're driver-neutral.)
- `LatteViewConfig` follows the same pattern as `ViewConfig`: readonly class, constructor takes `ConfigRepositoryInterface`, accessor uses `getBool('view.strict_types')` — no PHP-level defaults.
- The `conflict` value should be `*` (any version) to prevent mixing regardless of versions.
- The container will autowire `LatteViewConfig` (single `ConfigRepositoryInterface` constructor dep). No explicit binding needed in `module.php`.

## Requirements (Test Descriptions)
- [ ] `it ships a config/view.php file in the view-latte package`
- [ ] `it sets extension to .latte by default`
- [ ] `it sets strict_types to true by default`
- [ ] `it does not redeclare cache_directory (lives in marko/view shared config)`
- [ ] `it does not redeclare auto_refresh (lives in marko/view shared config)`
- [ ] `it declares a Composer conflict with marko/view-twig in composer.json`
- [ ] `LatteViewConfig::strictTypes() returns the configured bool value`
- [ ] `LatteViewConfig::strictTypes() throws ConfigNotFoundException when view.strict_types is missing`
- [ ] `LatteEngineFactory takes both ViewConfig and LatteViewConfig in its constructor`
- [ ] `LatteEngineFactory sets Latte strict types from LatteViewConfig`
- [ ] `LatteEngineFactory continues to set cache directory and auto refresh from ViewConfig`

## Acceptance Criteria
- `packages/view-latte/config/view.php` exists and contains exactly `extension` and `strict_types` keys
- `packages/view-latte/src/LatteViewConfig.php` exists as a readonly class with `strictTypes(): bool`
- `packages/view-latte/src/LatteEngineFactory.php` constructor: `__construct(ViewConfig $viewConfig, LatteViewConfig $latteViewConfig)`
- `packages/view-latte/composer.json` includes `"conflict": {"marko/view-twig": "*"}`
- All existing view-latte tests pass after updating mocks to include `LatteViewConfig`
- App-level config overrides still win
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
