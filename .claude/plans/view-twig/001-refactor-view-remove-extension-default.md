# Task 001: Refactor marko/view — remove extension and strict_types defaults from config

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
The shared `marko/view` interface package currently hardcodes `extension => '.latte'` and `strict_types => true` in its `config/view.php`. Both are driver-specific (extension is Latte-flavored; strict_types is a Latte-only feature flag) and bias the interface package toward Latte, violating Marko's interface/driver separation.

Remove both keys from `marko/view`'s config so it ships only truly driver-neutral defaults (`cache_directory`, `auto_refresh`). Each driver package will ship its own defaults via its own config file (task 002 for view-latte; task 008 for view-twig).

Additionally remove the `strictTypes()` accessor from `ViewConfig` entirely — it is Latte-specific and does not belong in the shared interface package. Task 002 introduces a new `LatteViewConfig` class in view-latte to host this Latte-only accessor.

The `extension()` accessor on `ViewConfig` MUST stay, because `ModuleTemplateResolver` (in `marko/view`) calls it during path resolution. The accessor remains; only the default value moves to driver packages. With no driver installed, calling `extension()` will throw `ConfigNotFoundException` — which is the correct loud-error behavior.

## Context
- Related files:
  - `packages/view/config/view.php` (remove `extension` and `strict_types` keys; keep `cache_directory` and `auto_refresh`)
  - `packages/view/src/ViewConfig.php` (remove `strictTypes()` method; keep `extension()`, `cacheDirectory()`, `autoRefresh()`)
  - `packages/view/tests/ViewConfigTest.php` (update — see Tests To Update below)
- The `extension()` method on `ViewConfig` must keep working because `ModuleTemplateResolver` calls it. The accessor stays; only the default value moves to driver packages.
- App-level config still wins via Marko's override priority (vendor < modules < app).

## Tests To Update
The current `ViewConfigTest.php` has the following assertions that will break and must be updated:
- The `createDefaultViewConfigRepository()` helper currently seeds `view.extension => '.latte'` and `view.strict_types => true` — remove those from the helper's defaults.
- `it('ViewConfig has strict types property', ...)` — delete this test entirely (accessor is being removed).
- `it('ViewConfig loads all properties from config repository', ...)` — drop the `strictTypes()` assertion.
- `it('ViewConfig uses default config values', ...)` — drop the `.latte` extension expectation and the `strictTypes()` assertion. This test should now only assert the two remaining shared defaults (`cache_directory`, `auto_refresh`).
- `it('uses FakeConfigRepository in ViewConfigTest', ...)` — drop `view.extension` and `view.strict_types` from the seed array.

## Requirements (Test Descriptions)
- [ ] `it does not include an extension default in the shipped view config`
- [ ] `it does not include a strict_types default in the shipped view config`
- [ ] `it keeps cache_directory as a shipped default`
- [ ] `it keeps auto_refresh as a shipped default`
- [ ] `ViewConfig::extension() throws ConfigNotFoundException when no driver has set view.extension`
- [ ] `ViewConfig::extension() returns the value when a driver config sets view.extension`
- [ ] `ViewConfig no longer exposes a strictTypes() accessor`

## Acceptance Criteria
- `packages/view/config/view.php` contains exactly two keys: `cache_directory` and `auto_refresh`
- `ViewConfig::extension()` still exists and reads from `view.extension` — no signature change
- `ViewConfig::strictTypes()` no longer exists
- All existing `marko/view` tests still pass (per the Tests To Update list above)
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
