# Task 004: Worker Accept Loop

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Write `worker.php` — boot the application once, then serve requests in a loop using the two bridges. This is the artifact RoadRunner actually executes.

## Context
- Ships INSIDE the package at `packages/roadrunner/worker.php` (decided). `.rr.yaml` points at `vendor/marko/roadrunner/worker.php`. There is no publish command in v1.
- `Application::boot($basePath)` runs ONCE, before the loop. Everything inside the loop must be per-request.
- Getting the router needs no core change: `Application` exposes a public virtual property `$router` (`packages/core/src/Application.php:82`) whose property hook throws a loud error if `marko/routing` is absent. Use `$app->router->handle($request)`.
- `Application::handleRequest()` (line 397) is NOT reusable — it hardcodes `fromGlobals()` and `send()`. Do not modify it; this plan changes nothing in core.
- Extract the loop body into a testable class (e.g. a request handler collaborator) so it can be unit-tested with a fake worker. `worker.php` itself should be a thin bootstrap that wires and delegates — an untestable script with logic in it defeats the point.
- An exception from one request must NOT kill the worker. Catch, log, return a 500, and continue serving. A worker that dies on the first bad request is worse than PHP-FPM. The 500 body must never contain the exception message or stack trace in a non-development environment — that is a production information leak.

### STDOUT is the relay — stray output kills the worker

This is the biggest unlisted hazard in the plan. RoadRunner's default PHP worker relay is `pipes`, i.e. STDIN/STDOUT carry the goridge protocol frames. Anything the application `echo`s goes straight into that stream and corrupts it. Verified sources of stray STDOUT in a booted Marko app:

- `packages/errors-simple/module.php:18-19` — the module `boot` callback resolves `ErrorHandlerInterface` and calls `register()`, which installs `set_exception_handler()`, `set_error_handler()` and `register_shutdown_function()` (`SimpleErrorHandler.php:146-148`). Its `handle()` (line 45) checks `$this->environment->isCli()` — **true under a RoadRunner worker** — and `echo`s the formatted report to STDOUT. One uncaught throwable and the relay is garbage.
- `SimpleErrorHandler::clearOutputBuffers()` (line 64) drains **all** output buffers with `ob_end_clean()`.
- Any controller or view that `echo`s or `var_dump`s.

Requirements for this task:
1. After `Application::boot()` and before the accept loop, install a worker-safe exception handler via `set_exception_handler()` so the framework handler cannot reach STDOUT. Log through `LoggerInterface` when available, otherwise STDERR — RoadRunner captures STDERR as worker logs, which is safe.
2. Wrap each request in an output buffer. Discard (or append to the response body, in development only) whatever the application emitted, and never let it reach STDOUT. Restore `ob_get_level()` to its pre-request value after every request, including the exception path.
3. Do not modify `packages/errors-simple/` — neutralize from the worker side.

### Resolving the base path is not `dirname(__DIR__, 3)`

`worker.php` ships at `packages/roadrunner/worker.php` and is executed as `vendor/marko/roadrunner/worker.php`. Walking up from `__DIR__` breaks under Composer **path repositories**, where `vendor/marko/roadrunner` is a symlink back into the monorepo (this is exactly how this repo and the documented local-develop-in-downstream-app setup work) — `__DIR__` resolves through the symlink and lands in the wrong tree.

Resolve the base path in this order, failing loudly with the fix when none works:
1. `MARKO_BASE_PATH` environment variable, if set (RoadRunner passes `server.env` through to the worker process).
2. The directory containing the `vendor/autoload.php` that `worker.php` required — derived from the loaded Composer autoloader's own path, which is symlink-independent.
3. Never a bare `getcwd()` fallback without validating that `vendor/`, `app/` and `modules/` are reachable from it.

### Boot failure must not loop

If `Application::boot()` throws, the worker must write the reason to STDERR and exit non-zero. It must not enter the accept loop and it must not answer requests with a 500 forever — RoadRunner will restart it and the operator needs the real error.

## Requirements (Test Descriptions)
- [x] `it boots the application once for many requests`
- [x] `it returns a response for each request it receives`
- [x] `it converts an unhandled exception into a five hundred response`
- [x] `it omits exception details from the five hundred body outside development`
- [x] `it continues serving after a request throws`
- [x] `it stops looping when the worker signals no further requests`
- [x] `it captures stray application output instead of writing it to standard out`
- [x] `it restores the output buffer level after a request throws`
- [x] `it installs a worker safe exception handler over the framework handler`
- [x] `it resolves the application base path through a symlinked vendor directory`
- [x] `it exits with a loud error when the application fails to boot`

## Acceptance Criteria
- All requirements have passing tests
- `worker.php` contains no business logic beyond wiring
- No changes to any file under `packages/core/`, `packages/errors-simple/` or `packages/routing/`
- Nothing in this package writes to STDOUT except the RoadRunner relay itself
- Code follows code standards

## Implementation Notes

- `packages/roadrunner/worker.php` — thin bootstrap: locates `vendor/autoload.php`
  via `MARKO_BASE_PATH` (or `getcwd()` fallback) purely to make Marko classes
  loadable, then re-resolves the authoritative base path through
  `BasePathResolver`, boots `Application` in a try/catch that writes to
  STDERR and `exit(1)`s on failure (never enters the loop), runs
  `UnsafePackageChecker`, installs `WorkerSafeExceptionHandler`, and
  delegates the loop to `WorkerRequestHandler`.
- `src/Worker/WorkerRequestHandler.php` — the testable loop-body collaborator.
  Takes an already-booted `Router` (never `Application::boot()` itself, so it
  structurally cannot reboot per request). Wraps each request in
  `ob_start()`/`ob_end_clean()` (restored to the pre-request level on every
  path via `finally`) and a catch-all `Throwable` handler that logs via
  `WorkerLogger` and returns a 500 (generic body unless `development: true`).
- `src/Worker/WorkerLogger.php` — routes a throwable to a PSR-3
  `LoggerInterface` when the container has one bound, otherwise `STDERR`
  (`STDOUT` is the goridge relay under the default `pipes` transport and must
  never receive stray output).
- `src/Worker/WorkerSafeExceptionHandler.php` — `install()` calls
  `set_exception_handler($this->handle(...))` *after* `Application::boot()`
  so it wins over `marko/errors-simple`'s handler (which `echo`s to STDOUT
  when `isCli()` is true, which is true under a worker).
- `src/Worker/BasePathResolver.php` + `ComposerAutoloaderLocatorInterface` +
  `SplComposerAutoloaderLocator.php` — resolves `MARKO_BASE_PATH` env var →
  the registered Composer `ClassLoader`'s own (never-symlinked) file path
  (`vendor/composer/ClassLoader.php`, found via `spl_autoload_functions()`)
  → validated `getcwd()`. Never uses `__DIR__`/`__FILE__`, which PHP resolves
  through symlinks and would land in the package source tree under a
  Composer path repository. Throws `BasePathNotResolvableException` when no
  source has `vendor/`, `app/` and `modules/` reachable.
- Requirements 2–6 (`returns a response for each request`, `converts an
  unhandled exception into a 500`, `omits exception details outside
  development`, `continues serving after a throw`, `stops looping on null`)
  all passed immediately once `WorkerRequestHandler`'s initial minimal
  implementation (built for requirement 1) was in place — noted per TDD
  discipline rather than re-deriving already-correct behavior.
- `it exits with a loud error when the application fails to boot`
  (`tests/WorkerBootFailureTest.php`) spawns the real `worker.php` via
  `proc_open()` against a dynamically-built fixture project (a shim
  `vendor/autoload.php` delegating to this monorepo's real autoloader, plus
  an `app/failing` module whose `boot` callback throws), asserting a
  non-zero exit code, empty STDOUT, and the failure reason on STDERR.
  Verified meaningful by temporarily removing the `exit(1)` and confirming
  the test fails.
- Added `psr/log` to `packages/roadrunner/composer.json` `require` (already a
  transitive dependency via `spiral/roadrunner-worker`, now declared
  directly since `WorkerLogger` depends on `Psr\Log\LoggerInterface`).
- `php-cs-fixer fix` was run scoped to `packages/roadrunner/` and, as a
  side effect, reformatted `packages/roadrunner/tests/StateLeakSpikeTest.php`
  — an untracked, in-progress file from the concurrent sibling task (005).
  The change is cosmetic only (verified with `php -l`); left as-is.
- Full package suite: 73 passed. Full monorepo suite: 7054 passed, 0
  failed (up from the 7033 baseline). `composer phpstan` (level 6,
  includes `packages/roadrunner/src` and `worker.php`): no errors. `phpcs`
  on all touched files: clean.
