# Plan: marko/view-twig — Twig template engine driver

## Created
2026-05-26

## Status
completed

## Objective
Create a new `marko/view-twig` driver package as a sibling to `marko/view-latte`, giving users a second template engine choice. Refactor the shared `marko/view` config so it no longer biases toward a specific driver, ensuring true interface/driver separation.

## Related Issues
none

## Discovery Notes

**Existing view ecosystem:**
- `marko/view` (interface package) defines `ViewInterface`, `TemplateResolverInterface`, `ModuleTemplateResolver`, `ViewConfig`, and exceptions
- `marko/view-latte` is the only existing driver. Its shape: `LatteView` (implements `ViewInterface`), `LatteEngineFactory` (builds `Latte\Engine` from `ViewConfig`), `ModuleLoader` (implements `Latte\Loader` to resolve `module::path` templates via `TemplateResolverInterface`), `Extensions/SlotExtension` (custom `{slot}` tag)
- `view/config/view.php` currently hardcodes `extension => '.latte'` — this biases the interface package toward Latte, violating the interface/driver split

**Engine-specific config approach (resolved during clarification):**
- All defaults live in driver-package config files (no PHP-level fallbacks — per Marko's "config files are the single source of truth" and "no silent fallbacks" principles)
- Driver-specific accessors live in driver packages — `TwigViewConfig` in `view-twig`, not in shared `ViewConfig`
- `marko/view` config keeps only truly shared keys (`cache_directory`, `auto_refresh`); each driver ships its own `config/view.php` adding the keys it needs
- Mutual exclusion enforced via Composer `conflict` declaration (loud at install time, not just runtime)

**Native Twig features (resolved):**
- No SlotExtension port — Twig has native `{% block %}`, `{% extends %}`, `{% embed %}`, `{% use %}`, `{% macro %}` that cover slot use cases
- Expose full native Twig; don't reimplement what Twig already provides

**Cross-cutting impact:**
- `marko/admin-panel` ships 5 `.latte` template files in `resources/views/`. A user installing `view-twig` instead of `view-latte` would have admin-panel break. **Scoped to a follow-up plan** (engine-specific sibling packages: `marko/admin-panel-latte`, `marko/admin-panel-twig`).
- Docs site has custom Latte syntax highlighting grammar. Twig is built into Shiki — adding `'twig'` to `langs` in `docs/astro.config.mjs` covers highlighting.
- `marko/skeleton` and `marko/framework` are already engine-agnostic (no view driver in `require`). Skeleton gets both options in `suggest` block.

**Scaling pattern (documented for future):**
- For UI packages that ship reusable templates: extract templates into engine-specific siblings (`marko/{package}-{engine}`)
- For application/custom modules: hard-dep on whichever engine the team chose — multi-engine support is only worth the cost for shipped reusable UI

**Core engine adoption policy (deferred to follow-up plan):**
- Adopting a template engine under the `marko/*` namespace commits Marko to maintaining template parity for that engine across all `marko/*` UI modules
- A `CrossEngineTemplateParityTest` will live in `marko/view`'s test suite (not `marko/framework`'s) — the test only runs when `marko/view` is installed, which is the correct gate (no view = no parity invariant)
- The test walks `marko/*` packages, finds every template under `resources/views/`, and asserts a sibling exists for every registered core engine
- This commitment implicitly limits how many engines Marko can adopt in core (the maintenance math caps it at 2–3 realistically). Community engines outside the `marko/*` namespace have no parity obligation.
- **Scoped to the follow-up admin-panel plan**, where the test will be meaningful (added alongside the actual admin-panel sibling-package extraction)

## Scope

### In Scope
- New package `marko/view-twig` at `packages/view-twig/`
- `TwigView` implementing `ViewInterface`
- `TwigEngineFactory` building `Twig\Environment` from typed config
- `ModuleLoader` implementing `Twig\Loader\LoaderInterface` (resolves `module::path` templates via shared `TemplateResolverInterface`)
- `TwigViewConfig` exposing Twig-specific settings: `extension`, `strict_variables`, `autoescape`, `debug`, `charset`, plus the shared `cache_directory` and `auto_refresh`
- `view-twig/config/view.php` shipping all default values (`extension: '.twig'`, `strict_variables: true`, `autoescape: 'html'`, `debug: false`, `charset: 'UTF-8'`, `cache_directory: '/tmp/views'`, `auto_refresh: true`)
- `view-twig/module.php` binding `ViewInterface => TwigView` via closure
- Composer `conflict` declaration between `view-twig` and `view-latte` (mutual exclusion at install time)
- Refactor `marko/view`: remove `extension` default from `view/config/view.php` (driver-neutral interface package)
- Refactor `marko/view-latte`: add own `config/view.php` shipping `extension => '.latte'` and other Latte-relevant defaults; add `conflict` declaration mirroring view-twig's
- Update `marko/skeleton` composer.json `suggest` block to list both `view-twig` and `view-latte` (Twig first for broader recognition)
- Docs site: register `twig` as a Shiki language in `docs/astro.config.mjs`
- Full test parity with `view-latte` test suite (PackageTest, ModuleTest, TwigEngineFactoryTest, TwigViewTest, ModuleLoaderTest)

### Out of Scope
- `marko/admin-panel` template extraction into engine siblings — separate follow-up plan
- Other UI packages (none currently ship templates, so no work needed)
- Interactive engine selection in `composer create-project`
- README files and docs site content pages (handled by `doc-updater` agent in post-implementation pipeline per project conventions)
- SlotExtension port (Twig has native blocks/embed)
- Twig extensions beyond the engine defaults (users can add their own via DI or follow-up plans)

## Success Criteria
- [ ] `composer require marko/view-twig` installs cleanly into a Marko app
- [ ] `composer require marko/view-twig` is rejected by Composer when `marko/view-latte` is already installed (and vice versa)
- [ ] `ViewInterface` resolves to `TwigView` when view-twig is the installed driver
- [ ] Module-namespaced templates (`module::path/to/template`) render correctly with Twig
- [ ] Undefined template variables throw a Twig error (strict_variables enabled by default)
- [ ] HTML output is auto-escaped by default
- [ ] All defaults overridable via app-level `config/view.php`
- [ ] `marko/view` config no longer presumes Latte
- [ ] `marko/view-latte` continues to work with no behavior change after refactor
- [ ] Twig code blocks in docs site are syntax-highlighted
- [ ] All tests passing (`composer test`)
- [ ] Code follows project standards (php-cs-fixer, phpcs)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Refactor marko/view: remove extension + strict_types defaults from config; remove strictTypes() accessor | - | pending |
| 002 | Refactor marko/view-latte: ship own config + add LatteViewConfig + conflict declaration | 001 | pending |
| 003 | Scaffold marko/view-twig package structure + composer.json | - | pending |
| 004 | Implement TwigViewConfig with typed accessors | 003 | pending |
| 005 | Implement TwigEngineFactory | 004 | pending |
| 006 | Implement ModuleLoader for Twig | 003 | pending |
| 007 | Implement TwigView | 005, 006 | pending |
| 008 | Ship view-twig config/view.php with defaults | 003 | pending |
| 009 | Wire view-twig module.php bindings | 007, 008 | pending |
| 010 | Add Twig syntax highlighting to docs site | - | pending |
| 011 | Update skeleton composer.json suggest block | - | pending |
| 012 | Add marko/view-twig to NoDriverException driver package list | - | pending |

## Architecture Notes

**Interface/driver split is the load-bearing principle here.** The whole point of this plan is making `marko/view` genuinely driver-neutral. After the refactor:
- `marko/view`: defines interface, resolver, and config keys *shared by all drivers* (cache_directory, auto_refresh). `ViewConfig` keeps `cacheDirectory()`, `autoRefresh()`, and `extension()` (the extension accessor is used by `ModuleTemplateResolver` which lives in this package; the default value moves to drivers but the accessor stays).
- `marko/view-latte`: ships its own `config/view.php` with `extension => '.latte'` and `strict_types => true`. Introduces a new `LatteViewConfig` class hosting the Latte-only `strictTypes()` accessor (previously on shared `ViewConfig`). `LatteEngineFactory` now takes both `ViewConfig` and `LatteViewConfig`.
- `marko/view-twig`: ships its own `config/view.php` with `extension => '.twig'` plus Twig-specific keys. Provides `TwigViewConfig` with all Twig-specific accessors.

This symmetry — each driver hosting its own driver-specific config accessor class — is non-negotiable after the refactor. Otherwise Latte-specific concerns leak into the shared interface package, defeating the plan's stated goal.

**Config layering:** Marko's module discovery loads config files in priority order (vendor → modules → app). A user's `app/config/view.php` overrides whatever the driver package ships. This is the user override mechanism — no special API needed.

**No PHP-level defaults:** Every value `TwigViewConfig` exposes goes through `ConfigRepositoryInterface::getString()` / `getBool()` etc., which throw `ConfigNotFoundException` on missing keys. All defaults live in `view-twig/config/view.php`. This is non-negotiable per Marko's "loud errors / no silent fallbacks" principle.

**Twig Loader interface differs from Latte's.** `Twig\Loader\LoaderInterface` requires `getSourceContext(string $name): Source`, `getCacheKey(string $name): string`, `isFresh(string $name, int $time): bool`, `exists(string $name): bool`. The `ModuleLoader` implementation delegates path resolution to the shared `TemplateResolverInterface` (same as view-latte does), then adapts to Twig's interface.

**Composer conflict for mutual exclusion:** Adding `"conflict": {"marko/view-latte": "*"}` to view-twig's composer.json (and mirroring in view-latte) means Composer refuses to install both — fails loudly at install time rather than silently letting one bind win. Aligned with "loud errors" principle.

## Risks & Mitigations

- **Risk:** Refactoring `marko/view-latte` to add its own config could break apps that override `view.extension` at the app level.
  **Mitigation:** App-level config has highest priority (overrides modules and vendor). App overrides continue to win. Driver-shipped config only fills in defaults — users who already set `view.extension` see no change.

- **Risk:** Composer `conflict` declaration could surprise existing view-latte users when they try the new package.
  **Mitigation:** The error message is loud and actionable ("remove marko/view-latte before installing marko/view-twig"). This is the correct UX — mixing two `ViewInterface` bindings was never supported and would have caused `BindingConflictException` at runtime anyway. Failing at `composer install` time is strictly better.

- **Risk:** Twig autoescape defaults differ between Twig 2 and Twig 3. Twig 3 defaults to HTML autoescape from `{% autoescape %}` tags, but `Environment` constructor takes an `autoescape` option.
  **Mitigation:** Explicitly set `autoescape => 'html'` in our default config and pass it through `TwigEngineFactory`. Don't rely on Twig's internal defaults.

- **Risk:** admin-panel users who switch to view-twig will hit runtime template-not-found errors.
  **Mitigation:** Out of scope for this plan — handled by the follow-up admin-panel-sibling-package plan. Document the limitation in skeleton README (handled by doc-updater).

- **Risk:** Test parity with view-latte might miss Twig-specific edge cases (strict_variables behavior, autoescape behavior).
  **Mitigation:** Add Twig-specific tests beyond direct parity: verify undefined-variable error is raised, verify HTML escaping is active by default, verify users can disable autoescape via config.
