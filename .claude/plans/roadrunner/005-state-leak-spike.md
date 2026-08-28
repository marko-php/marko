# Task 005: State-Leak Discovery Spike

**Status**: completed
**Depends on**: 004a
**Retry count**: 0

## Description
Drive many requests through one booted application and empirically enumerate what leaks between them, then commit the findings as an artifact. This task deliberately DISCOVERS the reset requirements rather than designing them, so task 006 wires only what is real.

## Context
This is the pivotal task in the plan. The project's principle #5 is "no pseudo-functionality — don't build fake features to demonstrate concepts." Designing a `ResettableInterface` before observing real behaviour would be exactly that. Observe first, wire second.

**Use the task 004a harness.** It boots one `Application` and drives N `Request` objects through `$app->router->handle()`. That reproduces every in-process leak condition a RoadRunner worker has, with no PSR-7, no subprocess and no `rr` binary — which is why this task depends on 004a and not on 004. Do not wait for the worker.

**The session and auth guard leaks are already confirmed and are fixed in #150 task 009.** Record them in the findings document for completeness, but do not spend the spike on them. This task is about the leaks nobody has found yet.

### A pass is only meaningful if the search was exhaustive — check all of this

A spike that drives two requests and sees nothing will pass while real leaks remain. Enumerate mechanically:

1. **Every `singletons` declaration in the monorepo.** Seventeen `module.php` files declare one: `session-file`, `session-database`, `authentication`, `authorization`, `database`, `debugbar`, `inertia`, `layout`, `vite`, `docs`, `docs-markdown`, `docs-fts`, `lsp`, `mcp`, `devai`, `codeindexer`, and a codeindexer fixture. For each singleton class, list its mutable instance properties and decide whether any is request-derived. Record the verdict per class — this list is the spike's audit trail.
2. **Bindings registered via `Container::instance()` at boot**, which are singletons in practice even without a `singletons` key. `Application::initialize()` registers eight itself; `RoutingBootstrapper::boot()` registers `RouteCollection`, `RouteMatcherInterface` and `Router` (lines 58-67); `database-readwrite/module.php:44-45` registers `ConnectionInterface` and `TransactionInterface`. Route/matcher state is boot-time and should be *confirmed* safe, not assumed.
3. **Mutable class statics.** Verified complete across `packages/*/src`: `Debugbar::$current`, `GuidelinesWriter::$notices`, `TestCase::$registeredRoots`, `EntityCompanionStorage::$instance`. Only the first and last are runtime concerns.
4. **Superglobal readers outside `Request::fromGlobals()`.** Verified complete: `errors-advanced/src/RequestDataCollector.php:48-51` (`$_SERVER`/`$_GET`/`$_POST`/`$_COOKIE` with injectable fallbacks), `debugbar/src/Debugbar.php:517`, `debugbar/src/Controller/ProfilerController.php:92`, `debugbar/src/Collectors/RequestCollector.php:18-39`, `debugbar/src/Collectors/InertiaCollector.php:127`. Everything else is boot-time env reading. Under a worker these superglobals are frozen at the values the process started with — check what that means for each.
5. **Process-global PHP state that requests mutate**, none of which is a property on any object and none of which a singleton audit would find:
   - `register_shutdown_function()` accumulation — `Session::configure()` calls `session_set_save_handler($handler, true)` on every `start()`, and `SimpleErrorHandler::register()` registers one at boot.
   - `set_exception_handler` / `set_error_handler` stack depth.
   - `ob_get_level()` drift — `Debugbar::boot()` calls `ob_start()` (line 113); `SimpleErrorHandler::clearOutputBuffers()` drains all buffers (line 64).
   - `ini_set()` drift — `Session::configure()` sets six ini values per request (lines 236-241).
   - `session_status()` left `PHP_SESSION_ACTIVE` when a request throws before `save()`.
   - Timezone and locale (`date_default_timezone_set`, `setlocale`).
   - Open database transactions left uncommitted by a thrown request — the next request inherits them on a pooled connection.
   - `mt_srand`/`srand` seeding.
6. **Memory growth.** Drive several hundred requests and record RSS or `memory_get_usage()` at intervals. A slow leak that only shows after 500 requests is a production incident, and two requests will never surface it. Record the observed curve in the findings.
7. **Anything caching a `Request` or `Response` for the process lifetime.**

### Method

Drive sequential requests through the harness with DIFFERENT identities — different session cookies, different authenticated users, different query state, different routes — and assert that request N+1 sees none of request N's state. Interleave: authenticated → anonymous → different user is a stronger sequence than A → B, because the anonymous request is where a stale cached identity is most damaging.

**Deliverable**: `packages/docs-markdown/docs/packages/roadrunner-state-leaks.md`, alongside the other package documentation rather than a `docs/` directory inside the package (no other package has one, and package `.gitattributes` export rules do not account for it). It must list, for each item enumerated above: the service, whether it leaks, the mechanism, and what resetting it requires. A lead that turns out NOT to leak must be recorded as such — a negative result is a real result and stops the next person re-investigating it. Task 006 consumes this document.

## Requirements (Test Descriptions)
- [x] `it does not carry session data from one request into the next`
- [x] `it does not carry the authenticated user from one request into the next`
- [x] `it does not carry request scoped container state between requests`
- [x] `it does not accumulate shutdown functions across requests`
- [x] `it does not drift the output buffer level across requests`
- [x] `it leaves no active session when a request throws`
- [x] `it does not grow memory unboundedly across several hundred requests`
- [x] `it records every confirmed leak in the findings document`
- [x] `it records investigated leads that turned out not to leak`
- [x] `it records a verdict for every singleton declared across the monorepo`

## Acceptance Criteria
- All requirements have passing tests
- Findings document committed at `packages/docs-markdown/docs/packages/roadrunner-state-leaks.md` and readable by task 006
- Every singleton, boot-time `instance()` binding, mutable static, superglobal reader and process-global item enumerated above has an explicit verdict
- Each confirmed leak names the service, the mechanism, and the symptom
- Runs inside `composer test` — no `rr` binary required
- Code follows code standards

## Implementation Notes

All ten requirements share one Pest file
(`packages/roadrunner/tests/StateLeakSpikeTest.php`) plus the deliverable
doc (`packages/docs-markdown/docs/packages/roadrunner-state-leaks.md`) that
seven of the ten tests assert against directly — the doc-verification tests
were RED against a missing file (confirmed via `file_get_contents()`
returning `false`, surfacing as `InvalidExpectationValue`/`TypeError`
failures) until the doc was written, then went GREEN together. Three
behavioural tests (session-data isolation, auth-user isolation, and
container-scoped-state) passed on first run because the underlying
mechanisms (`Session`/`SessionGuard::reset()` from #150 task 009, and
`Container::resolve()` never caching non-singleton bindings) were already
correct — recorded as pre-existing, not implemented by this task, per the
"note it and move to next requirement" instruction for tests that pass
immediately.

**Fixture change**: added `GET /session/throw` to the shared
`DemoController` fixture (writes to the session, then throws) — needed to
exercise "session left active on throw" through the harness rather than
asserting it from source alone. No existing route or test was touched.

**Helpers.php additions** (fourth contributor, per 004a's note — appended,
not rewritten): `monorepoRootPath()` and `moduleSingletonIdentifiers()`. The
latter mechanically parses a `module.php`'s `singletons` key (`require`s the
file and normalizes both list-form `[Foo::class]` and keyed-form
`[Interface::class => Concrete::class]` declarations into short identifiers)
so `it records a verdict for every singleton declared across the monorepo`
cross-checks the findings doc against the actual 17 `module.php` files
(mechanically enumerated: `authentication`, `authorization`, `codeindexer`
+ its `vendor/foo/bar` test fixture, `database`, `debugbar`, `devai`,
`docs-fts`, `docs-markdown`, `docs`, `inertia`, `layout`, `lsp`, `mcp`,
`session-database`, `session-file`, `vite`) rather than trusting a
hand-maintained list that could silently drift from the source.

**Audit findings** (full detail and reset-requirement rationale in the doc
itself — see its "Summary for task 006" section):

- Confirmed `Leaks: Yes`, needing task 006 to wire a reset: `Inertia::$shared`
  (no `ResettableInterface` yet), `ReadWriteConnection` (existing `reset()`
  only clears the sticky-write flag, not an open transaction left by a
  thrown request).
- Confirmed `Leaks: Yes`, architectural (not fixable by a per-request
  reset): `Debugbar` (accumulating `$messages`/`$queries`/`$logs`/etc. on a
  singleton, plus a single `ob_start()` buffer that outlives the request it
  opened on), `DatabaseConnectionPlugin`/`ViewPlugin` (`$started` timing
  cache orphaned by a thrown query/render, corrupting a later unrelated
  request's recorded duration). All three are already covered by
  `UnsafePackageChecker::warnDebugbar()` (task 004) — the fix is "don't run
  `marko/debugbar` in a worker," not a reset.
- Confirmed `Leaks: No` (already fixed in #150 task 009, re-verified here):
  `Session`, `SessionGuard`, and the shutdown-function-accumulation guard on
  `Session::configure()`.
- Confirmed `Leaks: No` (negative results, recorded so nobody re-investigates):
  `RouteCollection`/`RouteMatcher`/`Router` (boot-time only, confirmed via
  harness that controller resolution is fresh per request), `PolicyRegistry`,
  `GateInterface`→`Gate`, `IndexCache`, `ModuleWalker`, `EntityMetadataFactory`,
  `DebugbarStorage`, `LoggerPlugin`, `FtsSearch`, `MarkdownRepository`,
  `SsrClient`, `HandleResolver`, `LayoutResolver`, `Vite`,
  `EntityCompanionStorage` (self-cleaning `WeakMap`), the five superglobal
  readers (frozen-not-leaking under a worker — a staleness bug, not a
  cross-request identity leak), `ini_set()` drift (idempotent re-application
  of the same config-derived values), timezone/locale and RNG-seeding calls
  (none exist anywhere in `packages/*/src`), and nothing anywhere caches a
  `Request`/`Response` instance beyond method scope.
- Memory curve: flat (4.00 MB constant) across 600 sequential
  `/session/write` requests through the harness, both with and without
  `reset()` called between requests — expected, since none of the services
  this fixture wires (`Session`, `SessionGuard`, `AuthManager`) hold
  unbounded per-request-keyed state. Does not contradict the `Debugbar`/
  `Inertia`/`DatabaseConnectionPlugin` findings above, which are confirmed
  by source-level analysis since those packages are not installed in the
  fixture app.

Verified: `pest packages/roadrunner/tests/StateLeakSpikeTest.php` → 10
passed (26 assertions); `pest packages/roadrunner/tests/ --parallel` → 71
passed, 1 pre-existing failure in `Worker/BasePathResolverTest.php` from
task 004's in-progress work (class not found — out of this task's scope,
per the sibling-worker note); `phpcs`/`phpstan` on every file this task
touched (`StateLeakSpikeTest.php`, `Helpers.php`, `DemoController.php`)
→ clean, except one pre-existing `missingType.iterableValue` error in
`Helpers.php`'s `createModuleRepository` (already documented as a leftover
from tasks 002/003 in 004a's own implementation notes, untouched by this
task); full `composer test` → 7053 passed, 0 failed (up from the 7033
baseline: +10 from this task, the rest from the concurrent sibling task).
