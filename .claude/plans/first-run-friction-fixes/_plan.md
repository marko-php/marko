# Plan: First-Run Friction Fixes

## Created
2026-06-24

## Status
completed

## Objective
Collapse the bootstrapping a fresh `marko/skeleton` app needs before a trivial "Hello World page" works — from ~80% of the effort down to near zero — by fixing the upstream packages, the skeleton, and the create-module skill. Derived from a verified analysis of the `acta` session.

## Related Issues
none

## Discovery Notes
Verified against source (not just the installed `acta` packages):

- **TestCase missing (#1):** `marko/testing/src/` has no `TestCase`; the `create-module` skill's `Pest.php.tmpl` does `use Marko\Testing\TestCase;` → fatal. `Application::registerAutoloaders()` / `registerPsr4Autoloader()` (private, `packages/core/src/Application.php:228-268`) is the runtime mechanism that registers `app/*` + `modules/*` PSR-4 — bare `pest` never runs it, so app-module classes don't resolve in tests. **Decision: TestCase registers autoloaders only (no full app boot).**
- **Session 500 (#2):** `SessionInterface` lives in `marko/session`, the same package whose `module.php` registers a global `SessionMiddleware` + eagerly binds `SessionInterface => Session`. `marko/testing` hard-requires `marko/session`, so any dev install activates that always-on middleware; with no driver bound it 500s even on a stateless route. The failure is a container `BindingException` (the middleware needs `SessionInterface => Session`, and `Session` needs the driver-only `SessionHandlerInterface`) — `marko/session` ships NO `NoDriverException`, so that label from the raw analysis is incorrect. Contrast: `marko/database/module.php` registers NO global middleware and guards `$container->has(TransactionInterface::class)` before touching a driver — it is inert when installed alone. **Decision: make `marko/session` passive like `marko/database` — move the `SessionInterface => Session` singleton, the `globalMiddleware`, and the page-cache `sequence` ordering OUT of `marko/session/module.php` and INTO the driver modules (`session-file`, `session-database`). Interface stays in `marko/session`. No new package. Loud error preserved (using a session with no driver → `BindingException`).** Note: `marko/testing` also drags in `marko/authentication`, `marko/mail`, `marko/queue`, `marko/log` on every dev install — these were verified to register only LAZY bindings and no global middleware, so session was the sole 500 source; no extra task needed for them.
- **No root harness (#3):** skeleton ships no root `phpunit.xml` and no app `tests/`; `.claude/testing.md` documents `pest --parallel` as THE command. Skeleton ships `app/.gitkeep`, `config/.gitkeep` only.
- **Resolver mis-diagnosis (#4):** `DependencyResolver::resolve()` (`packages/core/src/Module/DependencyResolver.php:112-115`) throws `CircularDependencyException` whenever Kahn's sort is incomplete, and `findCycle()` correctly returns `[]` when the incompleteness is NOT a real cycle → "Circular dependency detected: " with an empty chain. CORRECTION after source verification: a *disabled* required dependency is already caught loudly upstream at lines 42-49 (`ModuleException::missingDependency`) and never reaches that path; an *absent* `require` dependency is intentionally skipped (may be a plain Composer package) and does not destabilize the sort. The empty-chain path is actually reached by **soft-ordering (`after`/`before`) deadlocks**. Task 005 (a) redirects the existing disabled-dependency throw to the new `MissingDependencyException` (fixing its inaccurate "not installed" wording) and (b) replaces the empty-chain `CircularDependencyException` with a populated `MissingDependencyException` naming the unsorted modules + unmet ordering constraints, keeping `CircularDependencyException` (populated chain) for real cycles. This is the CLI `module:list`/`route:list` boot bug; HTTP/MCP paths enable a different module set and don't hit it.
- **Skill drift (#5):** `composer.json.tmpl` pins `^1.0`; a `composer.json.monorepo.tmpl` with `self.version` exists. Neither matches a downstream project consuming unreleased `dev-develop` packages. The skeleton itself requires `marko/framework: *`, which DOES resolve against dev-develop — so matching the host project's existing `marko/*` constraint style is the deterministic fix.

We explicitly rejected: shipping default drivers (violates "explicit over implicit"); a separate `marko/session-contracts` package (unnecessary once session is passive); and a runtime "no-op if no driver" conditional in the middleware (silent, violates "loud errors").

## Scope

### In Scope
- `marko/core`: extract reusable module-autoloader registration; split missing-dependency from true-cycle detection.
- `marko/testing`: ship `Marko\Testing\TestCase` (autoloaders only).
- `marko/session` + drivers: relocate session runtime registration to the drivers so the interface package is passive.
- `marko/skeleton`: ship a working root `phpunit.xml` + `tests/Pest.php` + `modules/` placeholder.
- `create-module` skill: make `Pest.php.tmpl` correct against the shipped `TestCase`, and derive `marko/*` version constraints from the host project.

### Out of Scope
- Shipping any default driver (session, errors, etc.).
- A new `marko/session-contracts` package.
- Full application boot inside `TestCase`.
- Tagging the monorepo to 1.0.

## Success Criteria
- [ ] `Marko\Testing\TestCase` exists; a test using it resolves `App\*` and `modules/*` classes under bare `pest` with no per-project Composer config.
- [ ] A fresh dev-mode skeleton (testing tools installed, no session driver) serves a stateless `GET /` with **no** `NoDriverException`/500.
- [ ] Installing `marko/session-file` (or `-database`) lights up sessions end-to-end (`Set-Cookie` present).
- [ ] Using a session with no driver installed throws a loud `BindingException` (not a silent skip).
- [ ] `DependencyResolver` throws a missing-dependency error naming the unsorted module(s) and unmet dep when there is no real cycle; throws `CircularDependencyException` with a populated chain only for a real cycle.
- [ ] A fresh skeleton runs `pest` green with zero setup.
- [ ] A module scaffolded by the skill `composer install`s against the host project's `marko/*` constraint style.
- [ ] All tests passing (`composer test`); lint clean on all touched files.
- [ ] Code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | core: extract reusable `ModuleAutoloader` (Application delegates to it) | - | completed |
| 002 | testing: ship `Marko\Testing\TestCase` (registers autoloaders) | 001 | completed |
| 003 | session: make `marko/session` passive (strip runtime registration) | - | completed |
| 004 | session drivers: register binding + global middleware + sequence | 003 | completed |
| 005 | core: split missing-dependency from circular-dependency detection | - | completed |
| 006 | skeleton: root `phpunit.xml` + `tests/Pest.php` (modules/.gitkeep already ships) | 002 | completed |
| 007 | create-module skill: align `Pest.php.tmpl` + host-derived constraints | 002 | completed |

## Architecture Notes
- **Passive interface package:** `marko/session` must mirror `marko/database` — interfaces + classes only, no always-on runtime registration. Drivers light up the feature. This is the framework's interface/driver contract made structural.
- **TestCase is lightweight:** no container, no drivers, no boot — it only registers `app/*` + `modules/*` PSR-4 autoloaders by reusing core's module discovery, then defers to PHPUnit's `TestCase`.
- **Loud over silent:** the resolver fix adds a *new* exception for the missing/disabled-dependency case rather than overloading `CircularDependencyException`; the session fix keeps `BindingException` as the loud failure when a session is used with no driver.
- **Deterministic skill output:** constraint selection is a rule (monorepo → `self.version`; else match host `marko/*` constraint, default `*`), not a guess.

## Risks & Mitigations
- **Moving session registration breaks consumers (authentication/security/inertia/layout) that assume `SessionInterface` is bound by `marko/session`:** Verified — all four resolve `SessionInterface` LAZILY (no global middleware), so a stateless `GET /` never triggers them; `marko/security`'s `CsrfTokenManagerInterface` factory does `$container->get(SessionInterface::class)` but only when CSRF is used. With no driver these now fail with a loud `BindingException` (desired). `marko/layout`'s `sequence.after: ['marko/session']` still references the present interface package and is unaffected. Mitigation: Task 003 explicitly runs the `marko/security`, `marko/authentication`, `marko/inertia`, and `marko/layout` suites (they fake sessions) and updates any test asserting the interface package registers the middleware/binding.
- **Both session drivers installed:** Not a new risk — `session-file` and `session-database` already both bind `SessionHandlerInterface` at `vendor` priority, so installing both already throws `BindingConflictException` (mutually exclusive by design). The relocated `SessionInterface => Session` binding adds no new conflict path; `GlobalMiddlewareResolver` de-dupes the duplicated middleware. Task 004 explicitly says NOT to add guard logic for this case.
- **`TestCase` project-root detection is fragile when tests run from a nested module dir:** walk up from the test location / cwd to the nearest ancestor containing `vendor/`; cover with a test that runs from a nested path.
- **Skill changes aren't PHP-testable via Pest:** validate via structural assertions (template references an existing class; emitted constraint resolves) and a `PackageStructureTest`-style check where a suite exists.
- **Resolver change alters an existing error type for some inputs:** keep `CircularDependencyException` behavior identical for genuine cycles; only the no-cycle-but-unsorted path changes. Cover both explicitly.
