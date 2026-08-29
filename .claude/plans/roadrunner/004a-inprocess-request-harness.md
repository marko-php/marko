# Task 004a: In-Process Multi-Request Test Harness

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Build the fixture application and the in-process driver that boots a real Marko app once and pushes N sequential `Request` objects through it. Tasks 005, 005a, 006 and 009 all need this and none of them define it; without it, "drive sequential requests with different identities" has no mechanism behind it.

This task can run in parallel with 002, 003, 007 and 008 — it needs no PSR-7 and no RoadRunner binary.

## Context
The whole security argument of this package rests on being able to observe request N+1 seeing request N's state. That observation does not require RoadRunner at all. It requires:

1. **One booted `Application`** — `Application::boot($basePath)` against a fixture project directory.
2. **Many `Request` objects driven through `$app->router->handle($request)`** — verified pure: `Router::handle(Request): Response` (`packages/routing/src/Router.php:38-40`).

Both seams already exist. `Application` exposes a public virtual property `$router` (`packages/core/src/Application.php:82`) whose property hook throws a loud `RuntimeException` when `marko/routing` is absent, and `public private(set) ContainerInterface $container` (line 64). No core change is needed for this.

### The fixture application

`packages/roadrunner/tests/Fixtures/app/` (or similar) must be a minimal but *real* Marko project directory that `Application::boot()` can consume: `vendor/`, `modules/`, `app/` with at least one app module declaring routes. It must exercise the services that actually hold request state:

- **Session** — `marko/session` + `marko/session-file`. `session-file/module.php:16-18` binds `SessionInterface` as a **singleton** and registers `SessionMiddleware` as global middleware.
- **Authentication** — `marko/authentication`. `authentication/module.php:25-28` marks `AuthManager` and `GuardInterface` as **singletons**.
- Routes that write to the session, read the authenticated user, and echo both back in the response body so a leak is directly assertable from the `Response`.

Study `packages/codeindexer/tests/Fixtures/MiniMonorepo/` for the shape of an existing fixture project tree in this repo, and `packages/testing/src/TestCase.php` for how the suite already registers fixture roots (note its `private static array $registeredRoots` at line 22 — the harness must not fight it).

### The driver

A small class that owns the booted `Application` and exposes something like `handle(Request): Response`, plus an optional reset hook that task 006 plugs into. It must:

- Boot exactly once for the lifetime of the harness instance.
- Return the real `Response`, not an assertion helper — the tests assert against `body()`, `statusCode()`, `headerLines()`.
- Allow driving requests with different cookies (so different session IDs) via the `Request` cookie parameter #150 adds.
- Be usable from a plain Pest test with no RoadRunner binary and no subprocess.

### Deliberately not the worker

Do NOT couple this to `WorkerRequestHandler` from task 004. The harness is about the *application's* per-request behaviour; the worker is about the PSR-7 boundary and the relay. Keeping them separate is what lets task 005 start without waiting for 002/003/004.

## Requirements (Test Descriptions)
- [x] `it boots the fixture application exactly once across many requests`
- [x] `it returns a response for each request driven through the harness`
- [x] `it drives requests carrying different cookies`
- [x] `it exposes the booted application container to the caller`
- [x] `it exposes a reset hook that runs between requests`
- [x] `it requires no roadrunner binary`

## Acceptance Criteria
- All requirements have passing tests
- The fixture app boots `marko/session`, `marko/session-file` and `marko/authentication`
- Harness runs inside `composer test` (no `integration-destructive` group)
- No file under `packages/core/` is modified
- Code follows code standards

## Implementation Notes

All six requirements share one fixture + one driver, so they were built together
rather than one-RED-test-at-a-time — no single test could pass without the full
fixture tree (vendor modules, app module, config) already in place. Each test
was verified failing (missing class/fixture) before the fixture+harness existed,
then all six went green together once both were complete; none passed
prematurely on partial implementation.

**Fixture** — `packages/roadrunner/tests/Fixtures/app/`:
- `vendor/marko/{config,session,session-file,authentication}/` — each a
  `composer.json` (+ `module.php` where the real package has one) that mirrors
  the corresponding real package's module wiring verbatim (copied bindings, not
  symlinked, so the fixture is self-contained and versioned like
  `packages/codeindexer/tests/Fixtures/MiniMonorepo/`). The real classes
  (`Marko\Session\Session`, `Marko\Authentication\AuthManager`, etc.) are
  already autoloaded via the monorepo root's `vendor/autoload.php` (root
  `composer.json` requires every package as a path repository), so the fixture
  vendor tree only needs metadata + wiring, no `src/`.
  - `marko/config` reproduces `ConfigRepositoryInterface`'s binding closure —
    without it `SessionConfig`/`AuthConfig` can't resolve.
  - `marko/session-file` binds `SessionInterface` as a singleton and registers
    `SessionMiddleware` globally (the leak surface tasks 005/006 need).
  - `marko/authentication` marks `AuthManager` and `GuardInterface` as
    singletons (the other leak surface).
- `config/session.php` — overrides the file-store path to
  `sys_get_temp_dir() . '/marko-roadrunner-harness/' . getmypid() . '/sessions'`
  (deterministic per PHP process, not random — `ConfigRepositoryInterface`
  isn't bound as a singleton in production `module.php`, so this file can be
  re-`require`d multiple times per boot and must resolve to the same path
  every time). `config/authentication.php` mirrors the real package defaults.
- `app/demo/` — the one real app module. `module.php` binds
  `UserProviderInterface` to `marko/testing`'s `FakeUserProvider` with one
  `FakeAuthenticatable` (id `1`). `DemoController` exposes `GET /session/write`
  (increments a session counter, logs the fixture user in) and
  `GET /session/read` (reads the counter and the current auth id/`'guest'`),
  echoing `session=<id>;visits=<n>;user=<id|guest>` into the response body —
  directly assertable, per the task's cross-request-leak requirement.

**Driver** — `packages/roadrunner/tests/Support/InProcessRequestHarness.php`
(`Marko\Roadrunner\Tests\Support`, not `src/` — deliberately not shipped, not
coupled to `WorkerRequestHandler`/task 004): boots `Application::boot($basePath)`
lazily and memoizes it (`??=`), exposes `handle(Request): Response` proxying to
`$application->router->handle()`, `container(): Container` (narrows
`ContainerInterface` to the concrete `Container` so task 006 can reach
`resolvedInstances()`), and `reset(): void` which calls `->reset()` on every
currently-resolved `ResettableInterface` instance
(`container()->resolvedInstances(ResettableInterface::class)`) — opt-in, not
automatic between `handle()` calls, so cross-request state persists by default
(the whole point of the harness) unless a caller explicitly resets.

**Tests** — `packages/roadrunner/tests/Support/InProcessRequestHarnessTest.php`,
helpers added to the existing `packages/roadrunner/tests/Helpers.php` (already
registered in root `composer.json`'s `autoload-dev.files`, so no new
`composer dump-autoload` was needed). The "reset hook" test exploits
`SessionGuard::$cachedUser` — an in-process memoization never cleared by the
file-backed session store itself — to prove `reset()` has a real, observable
effect: a session that never logged in reads back `user=guest` after
`reset()`, where without it the guard's cached identity from the prior request
would leak through.

Verified: `pest packages/roadrunner/tests/ --parallel` → 52 passed; full
`composer test` (`pest -c phpunit.xml --parallel --exclude-group=integration-destructive`,
run with `-d memory_limit=2G` per the `composer test` script) → 7033 passed, 0
failed; `phpstan analyse packages/roadrunner/src packages/roadrunner/tests` →
0 errors in any file this task touched (5 pre-existing errors remain in
`tests/GuardRails/UnsafePackageCheckerTest.php` and the existing
`createModuleRepository` helper in `tests/Helpers.php`, both untouched
leftovers from tasks 002/003, out of this task's scope); `phpcs packages/roadrunner`
→ clean. No file under `packages/core/` was touched.
