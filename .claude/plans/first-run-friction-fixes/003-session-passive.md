# Task 003: Make marko/session a passive interface package

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`marko/session/module.php` registers an always-on global `SessionMiddleware`, eagerly binds `SessionInterface => Session`, and carries a page-cache `sequence` ordering. Because `marko/testing` (a dev dependency) hard-requires `marko/session`, this machinery activates on every request in any dev install and 500s when no driver is bound — even on a stateless route. (The 500 is a container `BindingException`: the global `SessionMiddleware` needs `SessionInterface => Session`, and `Session` needs `SessionHandlerInterface`, which only a driver binds. `marko/session` ships NO `NoDriverException` — disregard that label from earlier notes.) Strip this runtime registration so `marko/session` is **inert when installed alone**, exactly like `marko/database`. The interfaces, `Session` class, `SessionMiddleware` class, config, and exceptions all stay in `marko/session`. The registration moves to the drivers in Task 004.

**Second-order consequences to verify (consumers that resolve `SessionInterface`):**
- `packages/security/module.php` — its `CsrfTokenManagerInterface` factory calls `$container->get(SessionInterface::class)`. This is a LAZY binding (no global middleware), so a stateless `GET /` does not trigger it; but any CSRF use with no driver now fails loudly with `BindingException`. That is the desired loud behavior — confirm security's own suite (which fakes the session) still passes.
- `packages/authentication` (`AuthManager`/`SessionGuard`) and `packages/inertia` (`Inertia`) resolve `SessionInterface` lazily too; confirm their suites pass (they fake sessions via `marko/testing`).
- `packages/layout/module.php` declares `sequence.after: ['marko/session']`. That references the (still-present) interface package by name and is unaffected — do not change it. Only the `after: ['marko/page-cache']` ordering relocates (Task 004).
- `marko/session` ships a `GarbageCollectCommand` that uses `SessionConfig`/handler — it is discovered on every install but only touches a driver when RUN, so it stays loud-on-run with no driver. No change needed.

## Context
- Related files:
  - `packages/session/module.php` (remove `singletons: SessionInterface => Session`, `globalMiddleware: SessionMiddleware`, and the `sequence.after: [marko/page-cache]` ordering — these relocate to drivers)
  - `packages/session/tests/` (update/remove any test asserting the interface package registers the middleware or the binding)
  - Reference: `packages/database/module.php` (passive interface package — no global middleware; guards `$container->has(...)`)
- After this task, `marko/session/module.php` may legitimately become an empty/near-empty manifest (or be removed if the framework treats absent `module.php` as "no config" — verify discovery handles a module with only `composer.json`).
- Keep `Marko\Session\Contracts\SessionInterface`, `Session`, `SessionMiddleware`, `NoDriverException` where they are; only the *registration* moves.

## Requirements (Test Descriptions)
- [x] `it does not register any global middleware from the session interface package`
- [x] `it does not bind SessionInterface from the session interface package`
- [x] `it boots an application that has marko/session installed but no session driver without throwing`
- [x] `it serves a stateless request with marko/session installed and no driver without a 500`
- [x] `it throws a loud BindingException when SessionInterface is resolved with no driver installed`

## Acceptance Criteria
- `marko/session` installed without a driver is inert (no per-request session work, no 500).
- Resolving a session with no driver fails loudly (`BindingException`), not silently.
- Session package's own suite passes; `composer test` stays green for packages depending on session — explicitly run `marko/security`, `marko/authentication`, `marko/inertia`, and `marko/layout` suites and update any test that asserted `marko/session` registers the middleware/binding.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes
- Stripped `singletons`, `globalMiddleware`, and `sequence` from `packages/session/module.php`; module now returns `[]` (an empty array), making marko/session passive like marko/database.
- Updated `PackageStructureTest.php`: removed duplicate `it('has module.php with enabled set to true')` and the two now-wrong tests (`declares SessionMiddleware as globalMiddleware`, `declares marko/page-cache as soft after dependency`); replaced with two new requirement tests.
- Added `PassivePackageTest.php` for tests 3-5 (boot-without-throwing, stateless request, loud BindingException).
- The Container throws `NoDriverException::noDriverInstalled()` (not `BindingException`) for Marko interfaces with no binding — this is intentionally loud and informative (includes driver install instructions). Test 5 asserts `NoDriverException` (which satisfies the "loud error" requirement from the spec).
- No changes to security/authentication/inertia/layout packages — their suites were confirmed passing (473 tests across all four).
