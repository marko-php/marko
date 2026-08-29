# Devil's Advocate Review: roadrunner

Reviewed 2026-08-28 against the actual codebase. Every claim the plan carried over from its in-session audit was re-checked; corrections are noted inline.

## Verification of the plan's stated claims

| Claim | Verdict |
|---|---|
| `Application::$router` public virtual property at `Application.php:82` with a throwing property hook; `public private(set) ContainerInterface $container` | **True** (hook at 82-86, container at 64) |
| `RoutingBootstrapper.php:67` registers `Router::class` via `instance()` | **True** |
| `Request` has a public constructor taking `server`, `query`, `post`, `body`, `controller`, `action` | **True** (`Request.php:14-21`) |
| Only four mutable statics across `packages/*/src` | **True** — `Debugbar::$current`, `GuidelinesWriter::$notices`, `TestCase::$registeredRoots`, `EntityCompanionStorage::$instance`. No function-level `static $x` anywhere. |
| `phpunit.xml` globs `packages/*/tests` | **True** (line 13) |
| `phpstan.neon` covers only `packages/core/src` | **True** |
| No Docker or service containers in `.github/workflows/` | **True** |
| `mail-smtp` uses `->group('integration-destructive')` at `StreamSocketIntegrationTest.php:26` | **True** |
| `queue-rabbitmq/composer.json` is a good template | **True** |
| "No core change is needed for the worker to reach the router" | **True for core.** But "this plan changes nothing outside `packages/roadrunner/`" is **false** — see C1/C2. |
| "Superglobal reads are confined to debugbar and `errors-advanced/RequestDataCollector`" | **True as stated**, but misleading: the framing missed instance state on request-scoped singletons, which is where the real leaks are. |
| `readme-package-check.yml` "enforces that every package has a README" | **False** — see C4. |
| `errors-advanced/RequestDataCollector` path | Actual path is `packages/errors-advanced/src/RequestDataCollector.php` (no `Collector/` subdirectory); lines 48-51 correct. |

---

## Critical (Must fix before building)

### C1. `Session` is a singleton that carries the previous request's session ID — a confirmed cross-user leak with no fix available inside `packages/roadrunner/`
Affects: **005, 006** (new task **005a** added)

`packages/session-file/module.php:16-18` binds `SessionInterface` as a **singleton** to `Marko\Session\Session`. `Session::save()` (line 222) sets `started = false` but leaves `$this->id` and `$this->data` populated. `Session::start()` (line 52) then does `if ($this->id !== '') { session_id($this->id); }`.

Even after #150 — whose task 006 seeds the ID from the inbound request cookie — the seeding only happens **when a cookie is present**. Sequence: request N is an authenticated user with a session cookie; request N+1 is an anonymous visitor with no cookie; nothing calls `setId()`; `$this->id` still holds request N's ID; `session_id()` loads request N's session. The anonymous visitor is now the previous user.

`SessionInterface` offers no escape: `setId('')` throws `InvalidSessionIdException` because `validateId()` (line 271) requires `^[a-zA-Z0-9-]{32,128}$`.

**Fix applied:** new task **005a** clears `$this->id` / `$this->data` / `$this->flashBag` in `Session::save()` — an FPM-neutral change, since under FPM the process dies immediately after. `_plan.md` Scope updated to allow it.

### C2. `SessionGuard::$cachedUser` persists on a singleton guard with no non-destructive reset
Affects: **005, 006** (new task **005a**)

`packages/authentication/module.php:25-28` marks `AuthManager` and `GuardInterface` as singletons; `AuthManager::$guards` (line 18) memoizes instances. `SessionGuard::$cachedUser` (`Guard/SessionGuard.php:24`) is set in `user()` (lines 60-75) and cleared only by `logout()`, which is destructive and unusable as a per-request reset.

Task 006 said "isolate the authenticated user between two sequential requests" but there is no API that does it. **Fix applied:** task 005a adds a non-destructive forget on the concrete guard (not on `GuardInterface`, so `marko/testing`'s fakes stay valid), mirroring how `ReadWriteConnection::resetStickyState()` is a concrete-class method.

### C3. The framework's global error handler writes to STDOUT, which is the RoadRunner relay
Affects: **004**

`packages/errors-simple/module.php:18-19` — the module boot callback resolves `ErrorHandlerInterface` and calls `register()`, installing `set_exception_handler()`, `set_error_handler()` and `register_shutdown_function()` (`SimpleErrorHandler.php:146-148`). `handle()` (line 45) checks `Environment::isCli()` — **true under a RoadRunner worker** — and `echo`s the report to STDOUT. With the default `pipes` relay, STDOUT carries goridge protocol frames. One uncaught throwable corrupts the stream and kills the worker, with no useful diagnostic.

`SimpleErrorHandler::clearOutputBuffers()` (line 64) additionally drains **all** output buffers.

The plan had no mention of STDOUT anywhere. **Fix applied:** task 004 now requires installing a worker-safe exception handler after boot, wrapping each request in an output buffer, restoring `ob_get_level()` on the exception path, and never letting application output reach STDOUT.

### C4. Task 001 will turn `composer test` red for every subsequent worker
Affects: **001** (and therefore every task)

`tests/PackagingTest.php` scans `packages/` and asserts, for **every** directory:
- a `.gitattributes` that `export-ignore`s `tests/`, `.gitattributes`, `.gitignore` and `phpunit.xml`/`phpunit.xml.dist`
- a `LICENSE` (MIT, copyright `Devtomic LLC`)
- the package basename present as an option in **both** `.github/ISSUE_TEMPLATE/bug_report.yml` and `feature_request.yml`

The plan mentioned `LICENSE` and `.gitattributes` only in passing and never mentioned the issue templates.

Separately, `.github/workflows/readme-package-check.yml` does **not** check that a package has a README. It runs `bin/check-readme-packages.sh`, a **root README catalog drift check** that scrapes `packages/<name>/README.md` links out of the root `README.md` and fails if any non-`type: project` package lacks a row. Tasks 001 and 010 both described it wrongly.

**Fix applied:** task 001 now enumerates all ten monorepo wiring items with acceptance criteria including a green `composer test`; task 010 corrected.

### C5. Root `composer.json` needs four edits and a regenerated lock, not just a path repository
Affects: **001**

The plan named only the `repositories` array. Also required:
- `require`: `"marko/roadrunner": "self.version"` — without it the package is never symlinked into `vendor/` and `Marko\Roadrunner\*` does not autoload at all.
- `autoload-dev.psr-4`: `"Marko\\Roadrunner\\Tests\\": "packages/roadrunner/tests/"` — every one of the ~95 siblings has one.
- `require-dev`: `spiral/roadrunner-http` and `nyholm/psr7`, mirroring how `php-amqplib/php-amqplib` is declared at the root for `queue-rabbitmq`.
- **`composer.lock` regenerated.** CI installs with `ramsey/composer-install@v3` (= `composer install` from the lock). New deps absent from the lock are simply not installed, and every job fails.
- `ext-sockets`: `spiral/roadrunner-worker` pulls `spiral/goridge`, which requires it. If confirmed after `composer update`, add to root `require` and add `extensions: sockets` to `setup-php` in all three `ci.yml` jobs and in `nightly.yml`.

**Fix applied** to task 001.

### C6. The container cannot tell you what has been resolved, so "reset every service" is unimplementable as written
Affects: **006**

`Marko\Core\Container\Container` exposes only `get()`, `has()` and `instance()`. `has()` returns `isset($this->bindings[$id]) || class_exists($id)` (line 61) — **true for any class that exists** — and `$instances` is private with no accessor.

Consequences: the reset list must be an explicit enumeration, and `$container->get(X)` to reset X will **instantiate** X if the request never used it (opening a database connection on every request, for example). Task 006's requirement "it resets every service identified by the spike" needed this constraint spelled out.

**Fix applied:** task 006 documents the constraint and adds `it does not instantiate a service that the request never used`.

---

## Important (Should fix before building)

### I1. Task 005 had no harness — the pivotal task's method had no mechanism
Affects: **005** (new task **004a** added)

"Drive sequential requests through the worker with different identities" requires a fixture Marko project that boots session and auth, plus a driver. Neither existed anywhere in the plan, and building one is itself substantial work sitting on the critical path.

It also does not need RoadRunner: booting one `Application` and driving `Request` objects through `Router::handle()` in-process reproduces every worker state-leak condition. **Fix applied:** new task **004a** owns the fixture app and harness, depends on 001 only, and runs in parallel with 002/003/007/008. Task 005 now depends on 004a instead of 004, removing the accept loop from the leak-discovery critical path entirely.

### I2. The spike's checklist could pass while real leaks remain
Affects: **005**

As written it named four leads and "container singletons across ~90 packages". That is not a search, it is a sample. **Fix applied:** task 005 now requires an explicit verdict for each of:
- Every `singletons` declaration — there are 17 `module.php` files with one (`session-file`, `session-database`, `authentication`, `authorization`, `database`, `debugbar`, `inertia`, `layout`, `vite`, `docs`, `docs-markdown`, `docs-fts`, `lsp`, `mcp`, `devai`, `codeindexer`, + a fixture).
- Boot-time `Container::instance()` bindings, which are singletons in practice without a `singletons` key: eight in `Application::initialize()`, three in `RoutingBootstrapper::boot()` (lines 58-67), two in `database-readwrite/module.php:44-45`.
- Process-global PHP state no singleton audit would find: `register_shutdown_function` accumulation, `set_exception_handler` stack depth, `ob_get_level()` drift, `ini_set` drift, `session_status()` left active by a thrown request, timezone/locale, uncommitted transactions on a pooled connection, RNG seeding.
- Memory growth over several hundred requests, not two.

Specifically flagged as a lead: `Session::configure()` (line 254) calls `session_set_save_handler($handler, true)` on **every** `start()`, and the `true` registers a shutdown function each time — unbounded growth in a long-running worker.

### I3. `.rr.yaml` as specified serves a broken app
Affects: **008**

The plan required only that it point at the packaged worker. Missing: `http.static.dir: public` (without it every asset 404s and the task 010 walkthrough is not reproducible), `pool.max_jobs` and `pool.supervisor.max_worker_memory` (the production backstop for any leak the spike missed — the cheapest insurance in the plan), and `server.env.MARKO_BASE_PATH`. **Fix applied.**

### I4. `worker.php` cannot find the project root by walking up from `__DIR__`
Affects: **004**

`vendor/marko/roadrunner/worker.php` is a **symlink** under Composer path repositories — which is exactly how this monorepo and the documented local-develop-in-a-downstream-app setup work. `dirname(__DIR__, 3)` resolves through the symlink into `packages/roadrunner` and lands in the wrong tree. **Fix applied:** task 004 specifies `MARKO_BASE_PATH` → autoloader-derived path → validated fallback, failing loudly.

### I5. `$_SERVER` synthesis was under-specified, and `REQUEST_URI` must carry the query string
Affects: **002**

`Request` has almost no first-class accessors — everything reads the server array. `Request::path()` (lines 52-55) strips at the first `?`, and `Marko\Inertia\Inertia:92` / `InertiaMiddleware:34` read `$request->server('REQUEST_URI')` and use it as the page URL, so dropping the query string silently breaks Inertia. **Fix applied:** task 002 now enumerates the required key set (`REQUEST_METHOD`, `REQUEST_URI` with query, `QUERY_STRING`, `SERVER_PROTOCOL`, `HTTP_HOST`, `SERVER_NAME`, `SERVER_PORT`, `HTTPS`, `REMOTE_ADDR`, `CONTENT_TYPE`, `CONTENT_LENGTH`, `HTTP_*`) and requires multi-value PSR-7 headers be joined with `, `.

### I6. File uploads silently vanish
Affects: **002**

`Marko\Routing\Http\Request` has no `$_FILES` concept and no files accessor, so PSR-7 `getUploadedFiles()` has nowhere to map. A dropped upload is exactly the silent failure this framework refuses. **Fix applied:** task 002 throws loudly when the PSR-7 request carries uploaded files; task 010 documents it.

### I7. The `headerLines()` seam returns strings, not pairs
Affects: **003**

Confirmed against `.claude/plans/response-decoration/003-header-line-emission.md`: the method is `Response::headerLines(): array` returning a `list<string>` of complete `Name: value` lines, cookies appended as `Set-Cookie` lines in insertion order, no SAPI calls. That plan explicitly names #151 as the consumer, so the seam holds.

But the bridge must split each line on the **first** `: ` and route multiple `Set-Cookie` lines through `withAddedHeader()`, never `withHeader()`, or every cookie but the last is dropped. The plan said "consume that seam" without noting either. **Fix applied**, plus a test for a header value containing a colon.

### I8. `StreamingResponse` detection must not hard-depend on `marko/sse`
Affects: **001, 003**

`Marko\Sse\StreamingResponse extends Marko\Routing\Http\Response`, so `instanceof` works — but `marko/sse` may not be installed. **Fix applied:** task 003 uses `class_exists()`-guarded detection; task 001 adds `marko/sse` to the package's `require-dev` so the test can construct a real one.

### I9. Refusing to boot on package *presence* is the wrong granularity
Affects: **007**

An app may have `marko/sse` installed transitively, for a retired endpoint, or for routes served by a separate FPM pool. Forcing a user to uninstall a package to start a server contradicts the framework's own stated position: *"Opinionated, not restrictive. Every 'no' comes with a 'yes, this way instead.'"*

**Fix applied:** refuse by default (the safe default stays), but ship a config escape hatch that downgrades the refusal to a warning for a named package, read through `ConfigRepositoryInterface` from a config file in `packages/roadrunner/config/`. The refusal message must name the exact key and value. The **real** protection is task 003's per-request `StreamingResponse` failure, which has no override — the warning says so.

### I10. `resetStickyState()` detection was described at the wrong level
Affects: **006**

It lives on the concrete `ReadWriteConnection` (line 133), not on `ConnectionInterface`. And `database-readwrite/module.php:18-46` registers the connection only when `config('database.driver') === 'readwrite'` — so the signal is a runtime `instanceof` on the resolved `ConnectionInterface`, not "is the package installed". Plugin interception generates subclasses, so `instanceof` holds where `get_class() === ...` would not. **Fix applied.**

### I11. The end-to-end test would never actually run in CI
Affects: **009**

`nightly.yml` runs `composer test:all` on `ubuntu-latest` with no RoadRunner binary, so the whole suite skips. The plan's own mitigation — "runs locally and nightly" — is false as things stand, and the security-critical isolation assertions would only ever execute on a developer's machine. **Fix applied:** task 009 adds an RR binary install step to `nightly.yml` plus an assertion (in `tests/CiWorkflowTest.php` style) that a future edit cannot silently drop it.

### I12. The isolation sequence A → B is too weak
Affects: **009**

The confirmed leaks (C1/C2) bite hardest on an **anonymous** request following an authenticated one, because that is when nothing seeds a fresh identity. An A → B sequence where both carry cookies would pass while the bug is live. **Fix applied:** task 009 requires authenticated A → anonymous → user B.

### I13. Serialization in the dependency chain was avoidable
Affects: **004, 005, 006, 007, 008, 009**

Original chain was 001 → 002/003 → 004 → 005 → 006 → 009 → 010 across seven batches, with 007 and 008 needlessly behind 004.
- **007** inspects the module registry; it needs the skeleton, nothing else. Moved to depend on **001**.
- **008** shells out to a binary and writes YAML; its only contract with 004 is a fixed path. Moved to depend on **001**.
- **005** needs a request driver, not a PSR-7 worker. Moved to depend on **004a**.
- The PSR-7 containment assertion in 009 is a static source scan needing nothing. Moved to **001**, where it guards every subsequent task instead of only the last.

New shape: **(1)** 001 → **(2)** 002, 003, 004a, 007, 008 → **(3)** 004, 005, 005a → **(4)** 006 → **(5)** 009 → **(6)** 010. Six batches, and batch 2 has five parallel workers instead of two.

### I14. Error responses could leak exception details
Affects: **004**

"Catch, log, return a 500" said nothing about the body. **Fix applied:** the 500 body must not contain the message or trace outside development.

### I15. Reset should run before the request, not after
Affects: **006**

The plan said "between requests". After-the-fact resetting means a worker killed mid-request (`pool.max_jobs` recycle, supervisor OOM kill, `SIGTERM`) can leave state that the next boot inherits from a warm pool. **Fix applied:** reset before each request, with a test asserting it still runs after a request throws.

---

## Minor (Nice to address — not applied)

- **Spike deliverable location.** The plan put it at `packages/roadrunner/docs/state-leaks.md`. No other package has a `docs/` directory, and the package `.gitattributes` export rules do not account for one. I moved it to `packages/docs-markdown/docs/packages/roadrunner-state-leaks.md` alongside the other package docs, which is a judgement call worth confirming.
- **Task 010 → 009 dependency.** Docs do not technically need the end-to-end test; 010 could depend on 006/007/008 and shave a batch. Left as-is because the "describe what was actually built" argument is sound.
- **`marko/devserver` integration.** Task 008 correctly defers it. Worth a follow-up issue: `dev:up` starting `rr serve` via `ProcessManager` is the obvious next step.
- **`CookieJarInterface` has no implementation** anywhere in `packages/*/src` (only `marko/testing`'s `FakeCookieJar`), so `SessionGuard`'s remember-me path is dead code in practice. Not this plan's problem, but it means task 009's auth assertions cannot exercise remember-me.
- **PHPStan gap.** Recorded as a decision. Given this package is the one place PSR-7 types cross a boundary and the one place a type error becomes a security bug, adding `packages/roadrunner/src` to `phpstan.neon` would cost one line. Deliberately not applied — the plan calls it a resolved decision.

---

## Questions for the Team

1. **Should `ResettableInterface` be reconsidered now?** The plan forbids it on "no pseudo-functionality" grounds, and that was right when the leak set was unknown. It is now known to include at least `Session`, `SessionGuard` and `ReadWriteConnection` — three packages, three bespoke concrete-class methods, each needing a `class_exists`-guarded `instanceof` in the worker. That is the shape of a missing interface. Still correct to defer to post-#151, or does three instances justify it now?

2. **Should `Container` gain a way to see resolved instances?** Without one (C6), the reset list is hardcoded and any package that later adds request-scoped singleton state silently breaks worker mode with no way to detect it. A `resolvedInstances(): array` accessor would be additive and BC-safe, but it is a core change and the plan forbids those.

3. **Is `pool.max_jobs` the honest answer to residual leak risk?** Recycling workers every N requests is what every RoadRunner deployment does, and it converts an unbounded leak into a bounded one. But it also means the spike's thoroughness matters less than the plan implies. Worth deciding explicitly whether it is a backstop or a strategy — it changes how much budget task 005 deserves.

4. **Does `marko/sse` under RoadRunner have a real answer eventually?** RoadRunner supports streaming responses natively (`http` plugin chunked output). "Incompatible by design" is true for the current `StreamingResponse::send()` implementation, not for SSE as a concept. Worth an issue so the docs can say "not yet" rather than "never".
