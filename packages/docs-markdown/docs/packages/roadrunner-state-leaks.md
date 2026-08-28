---
title: RoadRunner state-leak audit
description: Mechanical audit of every source of cross-request state in the monorepo, and the verdict on whether it leaks under a long-running RoadRunner worker.
---

Task 005's discovery spike for `marko/roadrunner`. A long-running worker keeps
one PHP process alive across many requests, so anything that survives a
request without being explicitly cleared is a candidate for leaking one
user's state into the next user's response. This document is the mechanical
audit trail: every container singleton, boot-time `instance()` binding,
mutable class static, superglobal reader and process-global PHP setting in
the monorepo, each given an explicit **Leaks: Yes** or **Leaks: No** verdict.

**Method.** `Marko\Roadrunner\Tests\Support\InProcessRequestHarness` (task
004a) boots one `Application` against a fixture project wiring
`marko/config`, `marko/session`, `marko/session-file` and
`marko/authentication`, then drives sequential `Request` objects through
`$app->router->handle()` with interleaved identities — authenticated,
anonymous, a different session — and several hundred requests for the memory
curve. See `packages/roadrunner/tests/StateLeakSpikeTest.php`. Services from
packages the fixture does not wire (debugbar, inertia, authorization, ...)
are audited by reading the source directly; that is called out per item
below, since it could not be exercised through a live request cycle in this
spike.

**Scope note.** `Session` and `SessionGuard` were already made
request-scoped and given `ResettableInterface` implementations in #150 task
009. They are listed below for completeness, not because this spike
discovered them.

Task 006 reads this document as the sole source of truth for what to wire
into the worker's per-request reset. It should wire every `Leaks: Yes` item
below and nothing else.

## 1. Container singletons (`singletons` in `module.php`)

Seventeen `module.php` files across the monorepo declare a `singletons` key.
For each, every mutable instance property on the declared class(es) is
listed with a verdict on whether it is request-derived.

| Package | Service | Leaks: | Mechanism / verdict |
| --- | --- | --- | --- |
| `marko/session-file` | `SessionInterface` → `Session` | **Leaks: No** (already fixed) | `Session` implements `ResettableInterface`; `reset()` clears `$id`, `$data`, `$flashBag`. Confirmed via harness: interleaved authenticated → anonymous → different-session requests never see a prior session's `visits` count once `reset()` runs between requests. Fixed in #150 task 009, not by this spike. |
| `marko/session-database` | `SessionInterface` → `Session` | **Leaks: No** (already fixed) | Same class as `session-file`; same verdict. Source read, not re-exercised (fixture only wires the file driver). |
| `marko/authentication` | `AuthManager` | **Leaks: No** | `$guards` is a `array<string, GuardInterface>` cache of already-constructed guard instances keyed by guard *name* ("web", "api", ...), not by request or user. The guard instances it caches are what carry per-request identity, and those are covered below. One caveat: only the guard resolved via `GuardInterface::class` (the container-registered default) is reachable through `Container::resolvedInstances(ResettableInterface::class)`; a guard resolved only via `$authManager->guard('other')` and never through the container directly would not be swept by a generic `ResettableInterface` reset loop. Not exercised in this spike (the fixture has one guard); flagged for task 006 as a design constraint, not a confirmed leak. |
| `marko/authentication` | `GuardInterface` → `SessionGuard` | **Leaks: No** (already fixed) | `SessionGuard` implements `ResettableInterface`. Confirmed via harness: interleaved authenticated → anonymous → different-session requests never see the previous request's `user=` id once `reset()` runs between requests — proven directly against the currently logged-in fixture user. Fixed in #150 task 009. |
| `marko/authorization` | `PolicyRegistry` | **Leaks: No** | `$policies` is `array<class-string, class-string>`, populated only by `register()` calls at module-boot time (throws `AuthorizationException` on duplicate registration — a boot-time invariant, not a per-request write path). No request ever calls `register()`. Source read only; `marko/authorization` is not wired into the fixture app. |
| `marko/authorization` | `GateInterface` → `Gate` | **Leaks: No** | `Gate` is constructed fresh from `AuthManager`/`PolicyRegistry` inside the binding closure and holds no declared mutable properties beyond its constructor-injected collaborators (`guard`, `policyRegistry`), both already covered. Source read only. |
| `marko/codeindexer` | `IndexCache` | **Leaks: No** | `$data` is a lazily-built, file-backed index of the *codebase* (modules, routes, config keys, ...), invalidated by comparing file mtimes against a persisted `trackedPaths` set — nothing here is derived from an HTTP request. Used by MCP/LSP tooling, not the request/response cycle. Source read only. |
| `marko/codeindexer` | `ModuleWalker` | **Leaks: No** | Holds only `readonly string $rootPath`. No mutable state. |
| `marko/codeindexer` test fixture | `FooBarService` (`vendor/foo/bar/module.php`) | **Leaks: No** | A fictional class name used only to exercise `codeindexer`'s own singleton-parsing tests; never instantiated, never installed in a real app. Out of scope by construction. |
| `marko/database` | `EntityMetadataFactory` | **Leaks: No** | `$cache` is `array<class-string, EntityMetadata>`, keyed and populated from static class reflection (`linkExtendersFrom()` at module `boot`), not from request data. Source read only; `marko/database` is not wired into the fixture app. |
| `marko/debugbar` | `Debugbar` | **Leaks: Yes** | `$messages`, `$openMeasures`, `$measures`, `$queries`, `$logs`, `$viewRenders` all accumulate via `record*()` calls made during request handling and are **never cleared**. Since `Debugbar` is a container singleton, every one of these arrays grows without bound for the life of the worker process across every request that touches a collector. Also: `boot()` is guarded by `$booted` so `ob_start()` (see §5) only runs once — but that single, never-closed output buffer then captures output from *every subsequent request* in the worker, not just the one active when `boot()` ran. Already flagged as worker-incompatible by `Marko\Roadrunner\GuardRails\UnsafePackageChecker::warnDebugbar()` (task 004): "a dev-only tool ... does not fit a long-running worker cleanly." Source read only — `marko/debugbar` is not wired into the fixture app and the guard rail already refuses to make it safe, only to warn. **Reset requirement**: not resettable in the `ResettableInterface` sense (the ob_start() buffer problem is architectural, not a per-request state problem) — do not enable `marko/debugbar` in a worker-served production environment, per the existing guard rail. |
| `marko/debugbar` | `DebugbarStorage` | **Leaks: No** | Only `readonly` constructor-injected collaborators (`ConfigRepositoryInterface`, `ProjectPaths`); all actual state lives in files on disk (`put()`/`get()`/`all()`/`clear()` are pure I/O), not in instance properties. |
| `marko/debugbar` | `DatabaseConnectionPlugin` | **Leaks: Yes** | `$started` is `array<string, list<float>>` keyed by `type . ':' . md5($sql)`, pushed in `beforeQuery()`/`beforeExecute()` and popped in `afterQuery()`/`afterExecute()`. If a request throws (or the query itself throws) between the `#[Before]` and `#[After]` hooks, the pushed timestamp is never popped and the entry stays in `$started` forever — both an unbounded memory leak (one stale array entry per aborted query, keyed by SQL content so it recurs for any repeated query text) and a correctness bug: a *later*, unrelated request running the same SQL text will `array_pop()` the stale timestamp left by the aborted request and record a nonsensical (too-long or negative-looking) duration for its own query. Source read only. **Reset requirement**: clear `$started` between requests, or key entries by a per-request correlation id instead of SQL content so a leftover entry cannot corrupt a different request's timing. |
| `marko/debugbar` | `LoggerPlugin` | **Leaks: No** | Only `readonly Debugbar $debugbar` — no mutable state beyond the `Debugbar` collaborator already listed above. |
| `marko/debugbar` | `ViewPlugin` | **Leaks: Yes** | Same `$started` push/pop-by-content-hash pattern as `DatabaseConnectionPlugin`, same failure mode on a thrown render, same reset requirement. |
| `marko/docs-fts` | `DocsSearchInterface` → `FtsSearch` | **Leaks: No** | `$pdo` is a lazily-opened, read-only connection to a static on-disk SQLite index (`resources/docs.sqlite`), not request-derived — holding it open across requests is the intended behaviour (a connection pool of one), not a leak of request data. |
| `marko/docs-markdown` | `MarkdownRepository` | **Leaks: No** | Only `readonly string $docsPath`. Docs content is read from disk per call, no cached request-derived state. |
| `marko/docs` | *(no singleton classes declared — `singletons => []`)* | **Leaks: No** | Nothing to audit. |
| `marko/inertia` | `Inertia` | **Leaks: Yes** | `$shared` is `array<string, mixed>`, written by the public `share()` API and merged into every subsequent `render()` call's props — but `Inertia` is a container singleton and nothing ever clears `$shared` between requests. A typical usage pattern (share the current authenticated user or flash data once per request, e.g. from middleware) would leave that data visible to every following request's Inertia response until the same key is overwritten, and if a later request shares a *different* key, both keys accumulate indefinitely. This is a genuine cross-request/cross-user data leak, not just a memory leak. Source read only — `marko/inertia` is not wired into the fixture app. **Reset requirement**: implement `ResettableInterface`, clearing `$shared` — the same shape as the `Session`/`SessionGuard` fix in #150 task 009. |
| `marko/inertia` | `SsrClient` | **Leaks: No** | `readonly class`; `render()` takes the page payload as a parameter and returns a result, storing nothing between calls. |
| `marko/layout` | `HandleResolver` | **Leaks: No** | No declared properties at all in the class body. |
| `marko/layout` | `LayoutResolver` | **Leaks: No** | No declared properties at all in the class body. |
| `marko/lsp` | `LspServer` | **Leaks: No** *(out of scope)* | `$initialized`/`$shuttingDown` track a stdio LSP server's own lifecycle for editor integrations — this singleton lives in a separate long-running process from any RoadRunner HTTP worker and never participates in the request/response cycle this package serves. Not applicable. |
| `marko/mcp` | *(no singleton classes declared — `singletons => []`)* | **Leaks: No** | Nothing to audit. `McpServer` itself is bound via a plain (non-singleton) closure. |
| `marko/devai` | *(no singleton classes declared — `singletons => []`)* | **Leaks: No** *(out of scope)* | CLI-only tooling (guideline generation), never boots inside an HTTP request cycle. |
| `marko/vite` | `Vite` | **Leaks: No** | Only non-promoted-readonly constructor collaborators (`ConfigRepositoryInterface`, `ProjectPaths`); asset-manifest reads are pure and re-read from disk per call, nothing cached in an instance property. |

## 2. Boot-time `Container::instance()` bindings

Registered once during `Application::initialize()` (or a module's `boot`
callback) rather than via the `singletons` array, but singletons in
practice — nothing ever re-registers them.

| Binding | Registered by | Leaks: | Verdict |
| --- | --- | --- | --- |
| `ContainerInterface` | `Application::initialize()` | **Leaks: No** | The container itself; already audited above (§3 in the source, `$resolving` is a call-scoped guard cleared in a `finally`, `$instances`/`$bindings`/`$shared` are boot-time-populated and by-design persistent — see "Container resolution" below). |
| `PluginInterceptor` | `Application::initialize()` | **Leaks: No** | Wraps class generation for plugin interception; holds no per-request state (verified: constructed once from `PluginRegistry`, which is itself populated only at boot). |
| `PluginRegistry` | `Application::initialize()` | **Leaks: No** | Populated once during module registration at boot; never mutated during request handling. |
| `ProjectPaths` | `Application::initialize()` | **Leaks: No** | `readonly`, derived from the boot-time base path. |
| `EventDispatcherInterface` | `Application::initialize()` | **Leaks: No** *(not exercised — no listener state observed in this spike; the dispatcher only holds boot-time-registered observer maps, not request data)* | Registered once; observers are registered at boot from `ObserverDefinition`s, not per request. |
| `ModuleRepositoryInterface` | `Application::initialize()` | **Leaks: No** | Backed by the boot-time module list; read-only after boot. |
| `CommandRegistry` | `Application::initialize()` (registered twice under the same key, once per discovery fork) | **Leaks: No** | CLI command definitions, populated at boot; not touched by HTTP request handling. |
| `RouteCollection` | `RoutingBootstrapper::boot()` (line ~58) | **Leaks: No — confirmed via harness** | `$routes` is populated once by `discoverRoutes()` during `boot()` and never written to again; `add()` is only ever called from that one boot-time pass. The harness drove hundreds of requests across many routes with no route-table mutation. |
| `RouteMatcherInterface` → `RouteMatcher` | `RoutingBootstrapper::boot()` (line ~63) | **Leaks: No — confirmed via harness** | Wraps the immutable `RouteCollection` above; `match()` is a pure read. |
| `Router` | `RoutingBootstrapper::boot()` (line ~67) | **Leaks: No — confirmed via harness** | `readonly class`; `handle()` resolves a fresh controller instance via `$this->container->get($matched->route->controller)` on every call (confirmed directly: two calls to `container()->get(DemoController::class)` in the same booted app return `!==` instances, since `DemoController` is not declared a singleton). Nothing about handling one request's controller is retained on `Router` itself. |
| `ConnectionInterface` → `ReadWriteConnection` | `database-readwrite/module.php:44` (boot callback, only when `database.driver === 'readwrite'`) | **Leaks: Yes (uncommitted transactions), No (sticky-write flag, already fixed)** | See §5 "Open database transactions" below — the `$stickyWrite` flag is already covered by `reset()` (#150 task 009), but `reset()` does **not** roll back a transaction left open by `beginTransaction()` when a request throws before `commit()`/`rollback()`. Source read only — `marko/database-readwrite` is not wired into the fixture app. |
| `TransactionInterface` → `ReadWriteConnection` | `database-readwrite/module.php:45` | *(same instance as above)* | Same verdict as `ConnectionInterface` above — one object registered under two interface keys. |

## 3. Mutable class statics

Four verified across `packages/*/src`. Only two are runtime concerns for a
RoadRunner worker; the other two never execute inside a served HTTP request.

| Static | Leaks: | Verdict |
| --- | --- | --- |
| `Marko\Debugbar\Debugbar::$current` | **Leaks: Yes** | Set in the constructor (`self::$current = $this`), read via `Debugbar::current()`/cleared via `Debugbar::forgetCurrent()`. Since `Debugbar` is only ever constructed once (a container singleton), this static simply mirrors that one instance's already-confirmed leak (§1) — nothing additional to reset here beyond resetting `Debugbar` itself, but `forgetCurrent()` exists and should be considered if `Debugbar` is ever rebuilt mid-process. |
| `Marko\DevAi\Writing\GuidelinesWriter::$notices` | **Leaks: No** *(out of scope)* | CLI-only (`devai:update` guideline generation); never runs inside a served HTTP request. |
| `Marko\Testing\TestCase::$registeredRoots` | **Leaks: No** *(out of scope)* | Test-suite bookkeeping only; irrelevant to a production worker process. |
| `Marko\Database\Entity\EntityCompanionStorage::$instance` | **Leaks: No** | Holds a single `WeakMap<Entity, array<class-string, Entity>>`. `WeakMap` entries are automatically removed by the garbage collector once the keyed `Entity` object itself is no longer referenced — since an `Entity` built for one request goes out of scope at the end of that request (nothing else retains it), its companion-bag entry is collected too. Self-cleaning by construction; no explicit reset needed. |

## 4. Superglobal readers outside `Request::fromGlobals()`

Verified complete. **Under a RoadRunner worker, `$_SERVER`, `$_GET`,
`$_POST` and `$_COOKIE` are frozen at whatever the worker process's PHP CLI
entrypoint started with** — confirmed by reading
`packages/roadrunner/src/Http/Psr7RequestBridge.php`, which builds `Request`
entirely from the incoming PSR-7 message and never touches a superglobal.
This changes the nature of every finding below: it is **not** a
cross-request identity leak (the values never change between requests, so
one user's data can't bleed into another's), but a **staleness/correctness**
bug — these code paths would show the same (effectively empty, CLI-derived)
snapshot for every request forever under a worker.

| Location | Leaks (cross-request identity): | Verdict |
| --- | --- | --- |
| `errors-advanced/src/RequestDataCollector.php:48-51` | **No** (staleness only) | Constructor accepts injectable `$server`/`$get`/`$post`/`$cookie` overrides, falling back to the superglobals only when the caller passes nothing. `PrettyHtmlFormatter` (`errors-advanced/src/PrettyHtmlFormatter.php:20`) does call `new RequestDataCollector()` with no arguments, so it *does* hit the frozen-superglobal path under a worker — the error page's "Request" section would show stale/empty data on every error, not another user's data. Not exercised in this spike (`marko/errors-advanced` not wired into the fixture app); recorded from source. |
| `debugbar/src/Debugbar.php:517` (`serverString()`) | **No** (staleness only) | Reads `$_SERVER[$key]` directly with no injectable override. Same frozen-value effect as above. Moot in practice: `marko/debugbar` is already flagged unsafe for worker mode (§1). |
| `debugbar/src/Controller/ProfilerController.php:92` (`serverHeader()`) | **No** (staleness only) | Same pattern, same package, same existing guard-rail mitigation. |
| `debugbar/src/Collectors/RequestCollector.php:18-39` | **No** (staleness only) | Reads `$_SERVER['REQUEST_METHOD']`, `$_SERVER['REQUEST_URI']`, `$_GET`, `$_POST`, and iterates `$_SERVER` for `HTTP_*` headers — all frozen under a worker. Same package, same existing mitigation. |
| `debugbar/src/Collectors/InertiaCollector.php:127` | **No** (staleness only) | Same pattern. |

## 5. Process-global PHP state (no singleton audit finds these)

| Item | Leaks: | Verdict |
| --- | --- | --- |
| `register_shutdown_function()` accumulation — `Session::configure()` | **Leaks: No — already fixed, confirmed via harness** | `Session.php` guards the `session_set_save_handler($this->handler, true)` call (which registers a shutdown callback as a side effect) behind a private `$handlerRegistered` flag, set once and never cleared by `reset()`. Confirmed by driving ten `/session/write` requests through the harness and asserting via reflection that `handlerRegistered` is `true` and the code path that flips it only ever runs once (the flag would prevent a second `session_set_save_handler()` call for the life of the process). |
| `register_shutdown_function()` — `SimpleErrorHandler::register()` / `AdvancedErrorHandler::register()` | **Leaks: No** | Both guard `register()` behind a `$registered` flag, and both are invoked exactly once, from a module `boot` callback (not from request handling) — confirmed by reading `errors-simple/module.php` and `errors-advanced/module.php`, whose `boot` closures call `$handler->register()` unconditionally but only run once per process (during `Application::initialize()`). |
| `set_exception_handler()` / `set_error_handler()` stack depth | **Leaks: No** | Same two handler classes as above; `register()`'s guard prevents re-registration, so the handler stack depth is fixed once at boot and never grows per request. `unregister()` exists and correctly calls `restore_exception_handler()`/`restore_error_handler()`, but nothing in the request path calls it. |
| `ob_get_level()` drift — `Debugbar::boot()` | **Leaks: Yes (architectural, already flagged)** | `boot()` calls `ob_start()` exactly once (guarded by `$booted`), but since it is never matched by a per-request `ob_end_*()`/`ob_get_clean()`, the buffer opened for the *first* request captures output from every request that follows in the same worker process. Already surfaced by `UnsafePackageChecker::warnDebugbar()` (task 004) as incompatible with a long-running worker. Confirmed clean by contrast: driving 20 requests through the harness (which does **not** wire `marko/debugbar`) shows `ob_get_level()` unchanged before and after — the drift is specific to Debugbar's `ob_start()`, not a property of request handling in general. |
| `ob_get_level()` drift — `SimpleErrorHandler::clearOutputBuffers()` | **Leaks: No** | Drains buffers down to level 0 (`while (ob_get_level() > 0) { ob_end_clean(); }`) only when rendering a fatal error page — a safety net that *reduces* drift, not a source of it. |
| `ini_set()` drift — `Session::configure()` (six calls, lines 239-244) | **Leaks: No** | All six calls re-apply the same config-derived values (`session.gc_maxlifetime`, `gc_probability`, `gc_divisor`, `use_strict_mode`, `use_cookies`, `use_only_cookies`) on every `start()`, computed fresh from `SessionConfig` each time — idempotent re-application of the same values, not drift from a previous request's mutated state. No other `ini_set()` call exists anywhere under `packages/*/src`. |
| `session_status()` left `PHP_SESSION_ACTIVE` when a request throws | **Leaks: No — already safe, confirmed via harness** | `SessionMiddleware::handle()` wraps `$next($request)` in `try { ... } finally { $this->session->save(); }`, and `Session::save()` calls `session_write_close()` unconditionally when `$this->started` is true. Confirmed directly: a new fixture route (`GET /session/throw`) writes to the session and then throws; driving it through the harness and catching the propagated exception, `session_status()` is `PHP_SESSION_NONE` immediately after — not left active. |
| Timezone / locale (`date_default_timezone_set`, `setlocale`) | **Leaks: No** | Neither function is called anywhere under `packages/*/src` in the monorepo (verified by grep). Nothing to reset. |
| Open database transactions left uncommitted by a thrown request | **Leaks: Yes** | `ReadWriteConnection::reset()` only calls `resetStickyState()` (clears `$stickyWrite`); it does **not** roll back an in-progress transaction. If a request calls `beginTransaction()` directly (not via the `transaction()` helper, which already wraps its work in `try/finally { $this->stickyWrite = false; }`) and then throws before `commit()`/`rollback()`, the underlying write connection is left with `inTransaction() === true` on a connection that is pooled across requests — the next request's writes on that connection are silently appended to the previous request's abandoned transaction. Source read only; `marko/database-readwrite` is not wired into the fixture app. **Reset requirement**: task 006 should call `rollback()` (guarded by `inTransaction()`) as part of resetting `ConnectionInterface`/`TransactionInterface` instances, in addition to the existing `resetStickyState()`. |
| `mt_srand()` / `srand()` seeding | **Leaks: No** | Neither function is called anywhere under `packages/*/src` in the monorepo (verified by grep). PHP's Mersenne Twister is auto-seeded per-process by default and nothing in this codebase re-seeds it, so there is no request-derived seed state to leak. |

## 6. Memory growth over several hundred requests

Driven directly (not through Pest, to get raw numbers for this document):
600 sequential `/session/write` requests through the harness, each with a
distinct session cookie, sampling `memory_get_usage(true)` every 100
requests.

**With `reset()` called between every request** (the shape task 006 wires):

| Requests | Memory (RSS-equivalent) |
| --- | --- |
| 0 | 4.00 MB |
| 100 | 4.00 MB |
| 200 | 4.00 MB |
| 300 | 4.00 MB |
| 400 | 4.00 MB |
| 500 | 4.00 MB |

**Without ever calling `reset()`** (today's unfixed-worker shape, for
comparison):

| Requests | Memory (RSS-equivalent) |
| --- | --- |
| 0 | 4.00 MB |
| 100 | 4.00 MB |
| 200 | 4.00 MB |
| 300 | 4.00 MB |
| 400 | 4.00 MB |
| 500 | 4.00 MB |

**Verdict: Leaks: No, flat curve — for the services this fixture wires**
(`Session`, `SessionGuard`, `AuthManager`). `AuthManager::$guards` only ever
holds one entry (the default "web" guard, cached by *name* not by request),
`Session::$data` holds a single overwritten `visits` key, and
`SessionGuard`'s cached user is a single reference reused across logins as
the same fixture user — none of these grow with request count, with or
without `reset()`. This does **not** contradict the confirmed leaks in §1
(`Debugbar`, `Inertia`, `DatabaseConnectionPlugin`/`ViewPlugin`) — those
packages are not installed in this fixture app, so their unbounded-growth
arrays (`Debugbar::$queries`, `$messages`, ...; `Inertia::$shared`;
`DatabaseConnectionPlugin`/`ViewPlugin::$started`) are not exercised here.
Those are confirmed by source-level analysis (§1) and the general shape of
the mechanism — every push with no matching pop, on a singleton, across
however many requests reach it — makes them unbounded regardless of this
fixture's flat curve. A production app that installs those packages would
need to reproduce this same memory-curve method against them directly.

The Pest test for this requirement (`it does not grow memory unboundedly
across several hundred requests`) drives 400 requests and asserts total
growth stays under a generous 25 MB bound — looser than the ~0 MB observed
above, so the test remains robust to environment-specific allocator
behaviour while still catching a real unbounded leak (which would blow far
past 25 MB over 400 requests).

## 7. Anything caching a `Request` or `Response` for the process lifetime

**Leaks: No.** Verified by grep across every `packages/*/src` directory:
no class declares an instance or static property typed `Request` or
`Response` outside a method parameter or local variable scope. The only
`static function fromGlobals(): self` on `Request` builds a fresh instance
per call and stores nothing statically.

## Summary for task 006

Confirmed `Leaks: Yes` items needing a per-request reset, beyond the
already-fixed `Session`/`SessionGuard`:

1. **`ReadWriteConnection`** — roll back an open transaction (`inTransaction()`
   guarded `rollback()`) in addition to the existing `resetStickyState()`.
2. **`Inertia::$shared`** — needs a new `reset()` (implement
   `ResettableInterface`) clearing `$shared`. Only relevant if
   `marko/inertia` is installed.
3. **`Debugbar`**, **`DatabaseConnectionPlugin`**, **`ViewPlugin`** — not
   resettable in a way that makes the package worker-safe (the `ob_start()`
   buffer problem on `Debugbar` is architectural). Covered by the existing
   `UnsafePackageChecker` guard rail (task 004): do not enable
   `marko/debugbar` in a worker-served production environment. No
   `ResettableInterface` wiring recommended for these three — the fix is
   "don't run this package in a worker," already enforced.

Everything else in this document is `Leaks: No` and should not receive
speculative reset wiring.
