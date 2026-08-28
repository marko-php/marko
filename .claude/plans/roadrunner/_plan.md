# Plan: RoadRunner Application Server Support

## Created
2026-08-28

## Status
completed

## Objective
Create a `marko/roadrunner` driver package that serves a Marko application under RoadRunner — booted once, serving many requests — with a per-request reset lifecycle proven by an empirical spike rather than guessed at, and loud guard rails against packages that are unsafe in worker mode.

## Related Issues
Closes #151
Depends on #150 (plan `response-decoration`)

## Discovery Notes

> **Re-verified against the codebase on 2026-08-28.** Corrections from that pass are marked **[corrected]**.

**No `marko/core` changes are required — but "no changes outside `packages/roadrunner/`" is false. [corrected]** `Application` already exposes a public virtual property `$router` (`packages/core/src/Application.php:82`) whose property hook throws a loud error when routing is absent, and `RoutingBootstrapper.php:67` registers `Router::class` in the container via `instance()`. `Application` also exposes `public private(set) ContainerInterface $container` (line 64). The worker loop is therefore `$app->router->handle($request)` against seams that already exist. `handleRequest()` (line 397) is not reusable in worker mode because it hardcodes `fromGlobals()` and `send()`, but nothing needs to change about it. **However**, `packages/session/` and `packages/authentication/` do need small changes — see the confirmed leaks below. **Those have been moved into #150** (plan `response-decoration`, task 009) so this plan stays purely additive; this plan consumes them.

**The request boundary is already pure.** `Request` (`packages/routing/src/Http/Request.php:14`) is readonly with a public constructor taking plain arrays (`server`, `query`, `post`, `body`, `controller`, `action`). `Response` exposes `statusCode()`, `headers()`, `body()` separately from `send()`. `Router::handle(Request): Response` (`packages/routing/src/Router.php:38-40`) is a pure function. `Request` has **no** files concept, so PSR-7 uploads have nowhere to map — task 002 fails loudly on them.

**Two cross-user leaks are already confirmed from source. [corrected]** The earlier audit's "very little global state" framing was too optimistic — it looked at statics and superglobals and missed instance state on request-scoped singletons:
- `SessionInterface` is a **singleton** (`packages/session-file/module.php:16-18`). `Session::save()` (line 222) leaves `$this->id` populated and `Session::start()` (line 52) does `session_id($this->id)`. After #150 the middleware seeds the ID from the inbound cookie *only when one is present*, so a request with no cookie inherits the previous request's session. `SessionInterface` has no way to clear it — `setId('')` fails `validateId()`.
- `AuthManager` and `GuardInterface` are **singletons** (`packages/authentication/module.php:25-28`) and `SessionGuard::$cachedUser` (line 24) is only cleared by the destructive `logout()`.

**Statics and superglobals — the earlier counts hold.** Four mutable statics across `packages/*/src`: `EntityCompanionStorage::$instance`, `Debugbar::$current`, `GuidelinesWriter::$notices`, `TestCase::$registeredRoots`; only the first two matter at runtime. Superglobal reads outside `Request::fromGlobals()` are confined to debugbar (five sites, dev-only) and `errors-advanced/src/RequestDataCollector.php:48-51`, which is constructor-injectable with `?? $_SERVER` fallbacks. Everything else is boot-time env reading. The middleware layer reads zero superglobals.

**STDOUT is the relay and the framework writes to it. [corrected]** `packages/errors-simple/module.php:18-19` registers a global exception handler at boot; `SimpleErrorHandler::handle()` (line 45) checks `Environment::isCli()` — true under a RoadRunner worker — and `echo`s the report to STDOUT, which is the goridge pipes relay. One uncaught throwable corrupts the protocol. Task 004 must install a worker-safe handler and buffer per-request output.

**The container cannot enumerate resolved instances. [corrected]** `Container::has()` returns `isset($bindings[$id]) || class_exists($id)` (line 61) — true for any existing class — and `$instances` is private with no accessor. The reset lifecycle must work from an explicit list; there is no "reset everything resolved" option without a core change, which this plan does not make.

**Verification is constrained by CI.** `.github/workflows/ci.yml` has no Docker or service containers, so an end-to-end RoadRunner test cannot gate every PR. The repo already has a precedent: `mail-smtp` marks its socket test `->group('integration-destructive')` (`StreamSocketIntegrationTest.php:26`), which `composer test` excludes. `phpunit.xml` globs `packages/*/tests` (line 13), so a new package's tests are auto-discovered with no phpunit config change. **But the monorepo `composer.json` needs four separate edits** (`repositories`, `require`, `require-dev`, `autoload-dev.psr-4`) plus a regenerated `composer.lock`, because CI installs via `ramsey/composer-install`. And `tests/PackagingTest.php` asserts every package has `.gitattributes`, a `Devtomic LLC` `LICENSE`, and an entry in **both** issue templates — a bare new directory turns `composer test` red for every subsequent worker. Task 001 owns all of it.

**`readme-package-check.yml` is a root-README catalog drift check, not a per-package README check. [corrected]** `bin/check-readme-packages.sh` scrapes `packages/<name>/README.md` links out of the root `README.md` and fails on drift. The row must land in task 001; the package README file itself is task 010.

**PHPStan now covers this package. [decision reversed]** `phpstan.neon` analyzed only `packages/core/src`. It is being extended with `packages/roadrunner/src` — this is the one package where a type error becomes a cross-user security bug, which outweighs the inconsistency of being the sole non-core package analysed.

**Most leaks are findable without RoadRunner. [corrected]** Boot one `Application` and drive N `Request` objects through `$app->router->handle()` in-process — that reproduces every worker state-leak condition with no binary, no subprocess and no PSR-7. Task 004a builds that harness, which is why the spike no longer waits on the accept loop.

### Resolved decisions (do not reopen)
- **Verification**: unit tests for the bridge, guard rails and reset lifecycle run in CI; the real end-to-end RoadRunner test is `->group('integration-destructive')`.
- **`worker.php` ships inside the package.** `.rr.yaml` points at `vendor/marko/roadrunner/worker.php`. Users needing custom boot behavior point their own `.rr.yaml` at their own file; no publish command in v1.
- **PHPStan is extended to `packages/roadrunner/src`** (reversing the earlier core-only decision).

### Inherited from #150 (already decided there)
- `Response` drops the `readonly` class modifier; `with*()` methods use `clone` and return `static`. Verified on PHP 8.5.1: `clone with` does not exist in 8.5.1, and both readonly-preserving approaches fail with "Cannot modify readonly property".
- Cookies are a separate `list<Cookie>` collection; `headers()` keeps `array<string, string>`.
- **Seam verified**: #150 task 003 (`.claude/plans/response-decoration/003-header-line-emission.md`) names the method **`Response::headerLines(): array`**, returning a `list<string>` of complete header lines — regular headers first, then one `Set-Cookie` per cookie in insertion order, with no SAPI calls. That task explicitly states the seam exists for this worker. Task 003 consumes it by name and must not reimplement `Set-Cookie` serialization.
- #150 adds `Request::cookie()` and makes `fromGlobals()` capture `$_COOKIE` (task 006a there). The cookie parameter is added last / named-only with a default, so the bridge constructs with named arguments.
- **#150 attaches the session cookie to the `Response` only when it changes** (new / regenerated / destroyed), because always-attaching would silently disable the page cache. The `Response` is therefore NOT a complete picture of session state on a repeat request, and this worker must not assume otherwise.
- **#150 now DOES make `Session` and `SessionGuard` request-resettable.** Its task 009 clears `Session::$id`/`$data`/`$flashBag` in `save()`, gives `SessionGuard` a non-destructive way to forget its cached user, and fixes the per-request shutdown-function accumulation. Its task 008 adds `ResettableInterface` to `marko/core`; its task 010 makes `ReadWriteConnection` implement it; its task 011 adds a `Container` resolved-instances accessor. This plan CONSUMES all four and implements none of them.

## Scope

### In Scope
- New `marko/roadrunner` package following driver-package conventions, fully wired into the monorepo (`composer.json` x4, `composer.lock`, `.gitattributes`, `LICENSE`, issue templates, root README catalog)
- PSR-7 to Marko `Request` bridge, and Marko `Response` to PSR-7 bridge
- `worker.php` accept loop, boot-once/serve-many, with STDOUT hygiene and a worker-safe exception handler
- An in-process multi-request harness and fixture app (task 004a) that later tasks build on
- `.rr.yaml` scaffolding and an `rr:serve` CLI command
- An empirical spike enumerating what leaks across requests, committed as an artifact
- Per-request reset lifecycle wired for whatever the spike identifies
- Loud guard rails: refuse to boot with `marko/sse` (with a documented config override); warn on debugbar; reset `database-readwrite` sticky state
- Docs page and package README

### Out of Scope
- Any change to `Response` / `Request` cookie or decoration APIs — those belong to #150
- Any change to `marko/core` — the seams already exist
- Any change to `SessionInterface` or `GuardInterface` — the contracts stay byte-identical so `marko/testing`'s fakes keep working
- `ResettableInterface`, the `Container` accessor, and the `Session`/`SessionGuard`/`ReadWriteConnection` changes — all moved to #150; this plan calls them, it does not build them
- Adding a container API to enumerate resolved instances; the reset list stays explicit
- Refactoring debugbar's superglobal reads (dev-only; warn instead)
- Making `marko/sse` work under the worker (incompatible by design)
- File upload support (`Request` has no files concept; fail loudly and document)

## Success Criteria
- [ ] A Marko app serves HTTP under RoadRunner with sessions, CSRF and auth intact
- [ ] An authenticated → anonymous → different-user request sequence through one worker shows zero identity bleed
- [ ] Spike findings committed, with an explicit verdict for every singleton, boot-time `instance()` binding, mutable static and process-global enumerated in task 005
- [ ] Reset lifecycle covers everything the spike identified
- [ ] Booting with `marko/sse` installed fails loudly with an actionable message that names the config override
- [ ] No PSR-7 symbol appears anywhere outside `packages/roadrunner/` (asserted from task 001 onward)
- [ ] Nothing in the request path writes to STDOUT
- [ ] Root README catalog row exists (enforced by `readme-package-check.yml`), package README exists, and a docs page ships
- [ ] `nightly.yml` installs the RoadRunner binary so the end-to-end suite actually runs there
- [ ] `composer ci` fully green

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding, monorepo wiring, PSR-7 containment test | - | completed |
| 002 | PSR-7 request to Marko Request bridge | 001 | completed |
| 003 | Marko Response to PSR-7 response bridge | 001 | completed |
| 004a | In-process multi-request test harness and fixture app | 001 | completed |
| 007 | Guard rails for worker-unsafe packages | 001 | completed |
| 008 | rr:serve command and .rr.yaml scaffolding | 001 | completed |
| 004 | Worker accept loop | 002, 003 | completed |
| 005 | State-leak discovery spike | 004a | completed |
| 006 | Per-request reset lifecycle | 004, 005 | completed |
| 009 | End-to-end integration test | 004, 006, 007, 008 | completed |
| 010 | Docs page and package README | 009 | completed |

Batches: **(1)** 001 → **(2)** 002, 003, 004a, 007, 008 → **(3)** 004, 005 → **(4)** 006 → **(5)** 009 → **(6)** 010.

## Architecture Notes
- The worker never calls `Response::send()`. It consumes `Response::headerLines()` (the #150 seam) plus `statusCode()` and `body()`, and maps them onto a PSR-7 response. `headerLines()` returns formatted strings, so the bridge splits on the first `: ` and uses `withAddedHeader()` for `Set-Cookie`.
- PSR-7 and the `spiral/roadrunner-http` / `nyholm/psr7` dependencies are confined to this package. `marko/core` has zero PSR-7 today and that invariant must hold — task 001 asserts it in the monorepo `tests/` suite so it guards every subsequent task.
- The reset lifecycle is discovered, not designed. Task 005 drives requests through the 004a harness and observes; task 006 only wires what 005 found. This follows CLAUDE.md principle #5 ("no pseudo-functionality"). The two leaks already confirmed from source are fixed in #150 task 009, so the spike spends its budget on the unknown ones.
- Leak discovery does not need RoadRunner. The 004a harness boots one `Application` and drives `Request` objects through `Router::handle()` in-process, which is why 005 runs in parallel with the accept loop instead of behind it.
- Guard rails follow framework principle #1: refusing to boot must explain what is wrong and how to fix it — including the config key that permits the boot, because "opinionated, not restrictive" means every "no" comes with a "yes, this way instead".
- `Application::boot()` runs once, outside the accept loop. Everything inside the loop must be per-request. Reset runs *before* each request, not after, so a crashed or killed request cannot poison the next one.

## Risks & Mitigations
- **A missed reset leaks one user's state into another user's request** — a security bug, not a glitch. Mitigation: two leaks are already confirmed and fixed in #150 task 009; task 005 is an empirical spike with a mechanically enumerated checklist (every `singletons` declaration, every boot-time `instance()` binding, every mutable static, every process-global) whose findings drive task 006; task 009 asserts isolation across an authenticated → anonymous → different-user sequence.
- **The spike passes while a slow leak remains.** Mitigation: task 005 drives several hundred requests and records the memory curve, not two requests; task 008's `.rr.yaml` ships `pool.max_jobs` and `pool.supervisor.max_worker_memory` as the production backstop for anything still missed.
- **Stray STDOUT corrupts the goridge relay.** `errors-simple` echoes to STDOUT under the CLI SAPI. Mitigation: task 004 installs a worker-safe exception handler and buffers per-request output; task 010 documents the restriction for application developers.
- **End-to-end coverage cannot gate every PR** (no Docker in CI). Mitigation: bridge, guard rails, harness, spike and reset logic all run in `composer test`; the end-to-end test is `integration-destructive`, and task 009 adds a RoadRunner binary install step to `nightly.yml` so it is actually exercised rather than perpetually skipped.
- **This plan is blocked on #150 landing first.** Mitigation: the dependency is explicit and named per task — 003 consumes `Response::headerLines()`, 006 consumes `ResettableInterface` and the `Container` accessor. Each will fail loudly at once if #150 has not landed.
- **PSR-7 leaking into core.** Mitigation: task 001 includes an assertion that no PSR-7 symbol appears outside this package.
- **A new package directory breaks `composer test` for every later worker** (`tests/PackagingTest.php`, `bin/check-readme-packages.sh`). Mitigation: task 001 lands all ten monorepo wiring items together and its acceptance criteria include a green `composer test`.
- **#150 must land first.** Mitigation: dependency is explicit; tasks 002/003 consume `Request::cookie()` and `Response::headerLines()` by name and will fail loudly if they are absent.
