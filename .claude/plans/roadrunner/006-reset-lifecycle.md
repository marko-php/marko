# Task 006: Per-Request Reset Lifecycle

**Status**: completed
**Depends on**: 004, 005
**Retry count**: 0

## Description
Wire a per-request reset for everything task 005's spike confirmed leaks. This is the security-critical part of the package: a missed reset means one user's session or identity bleeds into another user's request.

## Context
- Read `packages/docs-markdown/docs/packages/roadrunner-state-leaks.md` (task 005's deliverable) FIRST. Wire what it found and nothing else — do not add speculative resets for services the spike cleared.
- #150 task 009 already made `Session` and `SessionGuard` request-scoped at the source, and both implement `ResettableInterface`. This task **invokes** those seams from the worker; it does not reimplement them.
- The reset runs inside the accept loop, between requests, in the worker request handler from task 004. Reset **before** each request rather than after, so a request that throws or a worker killed mid-request cannot leave the next one with stale state.
- `ResettableInterface` lives in `marko/core` and is delivered by #150 task 008. Prefer discovering resettables through it over hardcoding a list: #150 task 011 adds a `Container` accessor returning already-resolved instances, so the worker can filter those for `ResettableInterface` and reset them generically.
- That accessor must never force instantiation, and neither must this reset — resetting a service that was never resolved for this request would construct it needlessly.
- Keep a loud fallback: if a service known to need resetting is absent from the resolved set when it should be present, fail rather than silently skipping it.
- This plan changes NOTHING outside `packages/roadrunner/`.
- Resetting must be loud on failure: if a service that should be resettable cannot be reset, fail the request rather than silently serving stale state. Silent degradation here is a cross-user data leak.
- Keep the reset ordering deterministic and documented — some resets may depend on others.

### SUPERSEDED — the container CAN now tell you what has been resolved

The section previously here said the container exposed no way to enumerate
resolved instances, and mandated a hardcoded reset list. **That is no longer
true.** #150 gained three amendments after this task was written:

- **Task 011** added `Container::resolvedInstances(?string $interface = null): array`,
  returning only instances the container has **already resolved**, optionally
  filtered to those implementing a given interface. It never forces
  instantiation.
- **Task 012** lifted that method onto `ContainerInterface`, so it is reachable
  through the type `Application::$container` is declared as. (This required
  updating container stubs in 15 test files across 9 packages — the interface
  really is the boundary now.)
- **Tasks 013 and 014** fixed the two leaks the spike found, at their source:
  `Inertia` now implements `ResettableInterface` clearing `$shared`, and
  `ReadWriteConnection::reset()` now rolls back an `inTransaction()`-guarded
  open transaction in addition to clearing sticky-write state.

**Use generic, container-driven discovery.** Filter
`$container->resolvedInstances(ResettableInterface::class)` and call `reset()`
on each. This is the decided approach, and it is why the accessor exists.

Consequences that still hold and must be respected:

- **Never call `$container->get(X)` to reset X.** That would instantiate X if the
  request never used it — opening a database connection on a request that never
  touched the database. `resolvedInstances()` returns only what is already
  built, which is exactly the point.
- **A service the request never resolved needs no reset**, by definition. Its
  absence from the result is correct, not a missing reset.
- `marko/inertia` and `marko/database-readwrite` need **no special-casing** in
  this package. They implement the contract; generic discovery picks them up
  when installed and resolved, and they are simply absent otherwise. Do not
  write `class_exists()` guards or `instanceof` checks for them.
- Plugin interception generates subclasses at runtime. `instanceof
  ResettableInterface` still holds through the generated subclass; a
  `get_class() === ...` comparison would not. `resolvedInstances()` filtering is
  `instanceof`-based, so this is handled.
- **Reset before each request, not after**, so a request that throws — or a
  worker killed mid-request — cannot leave the next one with stale state.
- **Loud on failure**: if a `reset()` throws, fail the request rather than
  silently serving stale state. Silent degradation here is a cross-user data
  leak.
- Ordering must be deterministic and documented.

### Not reset targets, by design

`Debugbar`, `DatabaseConnectionPlugin` and `ViewPlugin` leak but are **not**
made safe by resetting — `Debugbar::boot()`'s unclosed `ob_start()` is
architectural. They are covered by task 007's `UnsafePackageChecker`, which
warns that `marko/debugbar` does not belong in a worker-served environment. Do
not add reset wiring for them.

## Requirements (Test Descriptions)
- [x] `it resets every service identified by the spike between requests`
- [x] `it isolates session state between two sequential requests`
- [x] `it isolates the authenticated user between two sequential requests`
- [x] `it resets a resolved resettable service between requests`
- [x] `it skips a resettable service that the container never resolved`
- [x] `it does not instantiate a service that the request never used`
- [x] `it resets before the request rather than after`
- [x] `it still resets after a request throws`
- [x] `it fails the request loudly when a reset cannot be performed`
- [x] `it performs resets in a deterministic order`

## Acceptance Criteria
- All requirements have passing tests
- Every leak in the spike findings has a corresponding reset and test, or a recorded reason why it needs none
- Discovery is generic via `resolvedInstances(ResettableInterface::class)`; no package is special-cased and no service is instantiated to reset it
- No file under `packages/core/` is modified
- Code follows code standards

## Implementation Notes

`WorkerRequestHandler` (`packages/roadrunner/src/Worker/WorkerRequestHandler.php`)
now takes a required `ContainerInterface $container` and, inside `handleOne()`,
calls a private `resetResolvedServices()` **before** bridging/routing the
request, inside the same `try` block that already converts any `Throwable`
into a logged 500 — so a `reset()` failure is loud (logged + 500) exactly
like any other request failure, and the worker keeps serving. Discovery is
fully generic: `$this->container->resolvedInstances(ResettableInterface::class)`,
then `ksort()` on the returned `array<string, object>` before iterating, so
reset order is fixed (ascending by container binding identifier) and does
not depend on which services happened to resolve first for a given request.
No `get()`/`call()` is ever invoked to build something just to reset it.

`worker.php` now passes `container: $app->container` into the production
`WorkerRequestHandler`. All pre-existing `WorkerRequestHandlerTest.php`
cases were updated to pass `container: new NullContainer()` (already
returns `[]` from `resolvedInstances()`, so their behavior is unchanged).

One requirement — `it resets every service identified by the spike between
requests` — legitimately required the full mechanism (container injection,
pre-request reset placement, generic `ResettableInterface` filtering) to go
RED→GREEN. The remaining nine requirements (isolation, resolved/unresolved
discovery, no forced instantiation, before-not-after ordering, reset-after-
throw, loud reset failure, deterministic order) all passed immediately once
that generic implementation existed — each still got its own test written
first per the TDD checklist, and each is noted here as passing without
further implementation change, per the "note over-implementation and move
on" rule. This matches the task's own instruction to implement one generic
mechanism rather than special-casing each spike finding.

New test support added under `packages/roadrunner/tests/Worker/`:
`RecordingResettable` (reset-call spy with optional `onReset` callback for
ordering assertions), `FaultyResettable` (a `ResettableInterface` whose
`reset()` always throws), `StubResettableContainer` (a `ContainerInterface`
stub with a configurable `resolvedInstances()` set whose `get()`/`call()`
throw and count calls, proving the reset loop never reaches for them).
`tests/Helpers.php` gained `inProcessHarnessPsr7Request()`, the PSR-7
equivalent of the existing `inProcessHarnessRequest()`, for driving
`WorkerRequestHandler` (which only accepts PSR-7 requests) against the same
fixture application `InProcessRequestHarness` already uses.

Isolation tests for the two spike-confirmed, already-fixed services
(`Session`, `SessionGuard`) drive three interleaved requests — authenticated
→ anonymous → a different session — through a real `WorkerRequestHandler`
wired to a `WorkerRequestHandler`-external, once-booted `Application`
against the existing fixture app, per the task's explicit guidance that an
A→B sequence would not catch a stale identity surviving into an anonymous
request sandwiched between two authenticated ones.

Full roadrunner suite: 83 passed (170 assertions), up from the 73-test
baseline. `composer phpstan` (which scopes to `packages/core/src` and
`packages/roadrunner/src`): no errors. `phpcs`/`php-cs-fixer` run clean on
every touched file. No file under `packages/core/` was modified.
