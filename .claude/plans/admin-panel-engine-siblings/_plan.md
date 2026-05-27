# Plan: admin-panel engine-specific sibling packages

## Created
2026-05-27

## Status
planning

## Objective
Extract template files from `marko/admin-panel` into engine-specific sibling packages (`marko/admin-panel-latte`, `marko/admin-panel-twig`). Establish the `marko/{module}-{engine}` pattern as the canonical approach for UI packages that want multi-engine support. Add a `CrossEngineTemplateParityTest` in `marko/view` to mechanically enforce template parity across registered core view engines.

## Related Issues
Closes #93

## Discovery Notes

**Existing admin-panel state:**
- Ships 5 `.latte` templates under `resources/views/`: `auth/login.latte`, `layout/base.latte`, `dashboard/index.latte`, `partials/sidebar.latte`, `partials/flash.latte`
- Controllers (`DashboardController`, `LoginController`) reference templates via `$this->view->render('admin-panel::dashboard/index', ...)` — the `admin-panel::` module-namespaced syntax
- Controllers' tests mock `ViewInterface` — already engine-agnostic; no changes needed there
- `tests/Unit/Template/LayoutTemplateTest.php` (one file) verifies template content via `file_get_contents` and string `toContain` assertions — Latte-specific. Must move to `admin-panel-latte`; a parallel Twig-flavored equivalent goes in `admin-panel-twig`

**Critical architectural finding — template resolver namespace lookup:**

`ModuleTemplateResolver::matchesModuleName()` resolves `admin-panel::path` by finding modules whose basename equals `admin-panel`. After moving templates into `marko/admin-panel-latte`, the basename is `admin-panel-latte` — the resolver would NOT find them. This must be solved before templates move, or every controller breaks.

**Solution: `templates_for` composer-extra metadata.**

Sibling packages declare:
```json
"extra": {
    "marko": {
        "module": true,
        "templates_for": "marko/admin-panel"
    }
}
```

The resolver gains a second pass: in addition to matching by module basename, it ALSO scans modules with a `templates_for` declaration. When resolving `admin-panel::path`, any module declaring `templates_for: marko/admin-panel` is searched alongside `marko/admin-panel` itself. This is explicit (Marko principle), composable (third-party packages can use it), and doesn't invent new naming rules.

**`known-engines.php` — the parity test's source of truth:**

`packages/view/known-engines.php` registers the engines whose siblings the framework promises to ship. Format:
```php
return [
    'twig' => ['extension' => '.twig', 'driver' => 'marko/view-twig'],
    'latte' => ['extension' => '.latte', 'driver' => 'marko/view-latte'],
];
```

`CrossEngineTemplateParityTest` reads this and asserts: for every package declaring `templates_for: marko/X`, there exists an equivalent template provider for every OTHER registered engine targeting the same parent. Adopting a new core engine = signing up to ship template providers for every `marko/*` UI module — caught mechanically at build time.

**Twig templates must be hand-translated from Latte.** Latte and Twig syntax diverge enough that mechanical conversion isn't viable. Each of the 5 templates needs a from-scratch Twig version producing equivalent HTML.

**Scope coordination with PR #91 (known-drivers-registry):**

This plan is a dependency of PR #91. PR #91's task 025 (skeleton suggest consolidation) will pull in the two new packages from this plan once it merges. This plan does NOT touch skeleton's composer.json — that's PR #91's job.

## Scope

### In Scope

**Phase A — Infrastructure (parallel):**
- Add `packages/view/known-engines.php` registering Twig and Latte
- Enhance `ModuleTemplateResolver` to honor `extra.marko.templates_for` metadata when resolving module-namespaced templates
- Document the engine-sibling pattern in `.claude/architecture.md`

**Phase B — Scaffold new packages (parallel):**
- Create `marko/admin-panel-latte` package skeleton with `templates_for: marko/admin-panel` metadata; require `marko/view-latte`
- Create `marko/admin-panel-twig` package skeleton with `templates_for: marko/admin-panel` metadata; require `marko/view-twig`

**Phase C — Move and create templates:**
- Move 5 `.latte` files + `LayoutTemplateTest.php` from `marko/admin-panel` to `marko/admin-panel-latte`
- Hand-translate 5 templates into `.twig` for `marko/admin-panel-twig` producing equivalent HTML output
- Write `LayoutTemplateTest.php` equivalent in `marko/admin-panel-twig` asserting Twig structure

**Phase D — admin-panel cleanup:**
- Remove `resources/views/` directory from `marko/admin-panel`
- Update `marko/admin-panel`'s composer.json `suggest` block to list both engine siblings
- Verify all existing admin-panel tests still pass with no templates present
- Verify controllers continue to render via the resolver enhancement (integration test)

**Phase E — Parity enforcement:**
- Add `CrossEngineTemplateParityTest` to `packages/view/tests/Feature/` — scans all `marko/*` packages on disk, finds those declaring `templates_for`, asserts every engine in `known-engines.php` has a corresponding provider for each parent module
- Test skips gracefully when scanned packages aren't on disk (zero-dependency principle)

### Out of Scope

- Skeleton `composer.json` `suggest` block updates for the new packages — handled by PR #91 task 025 (this plan is its dependency)
- Adopting additional view engines (Blade, Liquid, etc.) — separate concern; the infrastructure in this plan supports it but actual adoption is not scoped here
- `marker interface` or `extra.marko.driver_for` enforcement for *drivers* — that's the deferred follow-up from PR #91; orthogonal to this plan's `templates_for` for *template providers*
- Stylesheet/asset coupling — admin-panel templates inline minimal styles; no shared CSS file moves with the templates. If a future refactor introduces shared assets, they live in `marko/admin-panel` (engine-agnostic).

## Success Criteria
- [ ] `composer require marko/admin-panel + marko/admin-panel-latte` works; admin panel renders correctly via Latte
- [ ] `composer require marko/admin-panel + marko/admin-panel-twig` works; admin panel renders correctly via Twig (HTML output is functionally equivalent to the Latte version)
- [ ] `composer require marko/admin-panel-latte marko/admin-panel-twig` (both installed) is harmless — the resolver routes to the right templates based on `view.extension` config; no conflict declaration enforces mutual exclusion
- [ ] `marko/admin-panel` has zero `.latte` or `.twig` files under `resources/views/` (directory removed entirely)
- [ ] `ModuleTemplateResolver` resolves `admin-panel::path` to templates in whichever sibling is installed
- [ ] `CrossEngineTemplateParityTest` passes (every template provider has siblings for every registered engine) and would fail if a `.twig` template is missing from `admin-panel-twig`
- [ ] `marko/view` tests pass standalone (no dependency on admin-panel or any specific engine)
- [ ] All existing admin-panel controller and config tests pass
- [ ] All tests passing (`composer test`)
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `known-engines.php` to marko/view | - | pending |
| 002 | Expose `extra` on `ModuleManifest`; enhance `ModuleTemplateResolver` to honor `templates_for` metadata | - | pending |
| 003 | Document engine-sibling pattern in architecture.md | - | pending |
| 004 | Scaffold `marko/admin-panel-latte` package | - | pending |
| 005 | Scaffold `marko/admin-panel-twig` package | - | pending |
| 006 | Move `.latte` templates and LayoutTemplateTest from admin-panel to admin-panel-latte | 002, 004 | pending |
| 007 | Hand-translate `.twig` templates into admin-panel-twig | 002, 005 | pending |
| 008 | Write `LayoutTemplateTest` equivalent for Twig templates | 007 | pending |
| 009 | Remove `resources/views/` from marko/admin-panel; update composer.json suggest | 006, 007 | pending |
| 010 | Add `CrossEngineTemplateParityTest` to marko/view | 001, 006, 007 | pending |

**Note on task 002 scope:** Task 002 spans two packages — `marko/core` (extending `ModuleManifest`, `ManifestParser`, `ModuleDiscovery` to surface `extra` from composer.json) and `marko/view` (the resolver enhancement). The core changes are a prerequisite for the view changes; both must ship together in this task. Worker should not attempt to split them.

## Architecture Notes

**Resolver enhancement signature.** `ModuleTemplateResolver::getSearchedPaths()` currently iterates `$this->moduleRepository->all()` and matches modules by basename. The enhancement adds a second match path: if a module's `extra.marko.templates_for` (read via `ModuleRepositoryInterface`) equals the requested template's parent, that module's `resources/views/` is also searched. Order matters — the parent module is searched first, the sibling provider second; this lets app-level overrides in `marko/admin-panel` (if any are ever added) win over the engine sibling.

**Why `templates_for` lives in composer.json `extra`, not `module.php`:** It's discoverable at install time by Composer tooling and by automated parity tests, without requiring PHP execution. Marko's `module.php` is for runtime DI wiring; this is package metadata describing the package's relationship to another package. composer.json is the right home for package-relationship metadata.

**Engine sibling vs driver — different patterns, different mutual-exclusion semantics:**
- *Drivers* (view-latte, view-twig) bind `ViewInterface` — installing both causes `BindingConflictException` at boot. The framework's loud-error path handles it.
- *Engine template siblings* (admin-panel-latte, admin-panel-twig) ship template files only. They do NOT bind any interface. Installing both is harmless: the `ModuleTemplateResolver` (after task 002) uses `view.extension` from config to know which extension to search for, so it naturally selects the right templates regardless of which siblings are installed.

Neither mechanism uses Composer `conflict` declarations. Drivers rely on DI-level detection; engine template siblings need no mutual-exclusion enforcement at all (worst case is wasted disk space, no behavior break). This is symmetric with how every other Marko multi-driver family handles the same condition — see PR #92 for the broader pattern.

**Parity test's contract.** The test answers: "if I install `marko/{X}-{engine}` for any engine, is every OTHER registered engine's sibling also available?" Failing the test means a contributor added a template provider without back-filling parity. This is the gate that makes adopting a new core engine a real commitment, not an aspiration.

## Risks & Mitigations

- **Risk:** Resolver enhancement is a behavior change in a load-bearing class (`ModuleTemplateResolver`). A regression could break template lookup for every controller using `module::path` syntax.
  **Mitigation:** New behavior is additive (extra match path). Old behavior preserved. Test coverage extends existing `ModuleTemplateResolverTest`. Plus the admin-panel flow itself serves as an integration test — if controllers can't find templates after the move, this immediately surfaces.

- **Risk:** Hand-translated Twig templates produce subtly-different HTML than the Latte originals (whitespace, attribute order, conditional rendering differences).
  **Mitigation:** Task 008 writes content-equivalence assertions covering the same structural elements as the Latte tests (DOCTYPE, form elements, blocks, includes). Plus the controllers' integration with the rendered output exercises real behavior. Acceptable risk: Twig and Latte have minor output differences (e.g., Twig's autoescape rules); functional equivalence (same elements, same data flow) is the contract, not byte-equivalence.

- **Risk:** Existing apps that installed `marko/admin-panel` standalone (assuming templates were bundled) will break after this refactor — admin routes will throw `TemplateNotFoundException`.
  **Mitigation:** This IS a breaking change. Document in PR description with the install-pattern migration: `composer require marko/admin-panel-latte` (or `-twig`) alongside existing `marko/admin-panel`. Mark as a minor version bump since admin-panel is pre-1.0. The skeleton's `suggest` block (PR #91) will guide new installs.

- **Risk:** The `templates_for` composer-extra key is novel — third parties don't yet know about it.
  **Mitigation:** Architecture doc update (task 003) documents the pattern explicitly. Future community modules can adopt the pattern by example.

- **Risk:** `CrossEngineTemplateParityTest` may produce noisy failures during plan rollout (e.g., if Twig templates aren't written before the test runs).
  **Mitigation:** Task ordering — task 010 (parity test) depends on tasks 006 AND 007 (both engine siblings populated). Test only meaningful once both providers exist.

- **Risk:** Removing `resources/views/` from `marko/admin-panel` may break the existing `LayoutTemplateTest.php` mid-migration if file moves and admin-panel cleanup are out of sync.
  **Mitigation:** Task 006 explicitly moves the test file alongside the templates. Task 009 (admin-panel cleanup) verifies the directory is empty after the move, with no orphaned test references.
