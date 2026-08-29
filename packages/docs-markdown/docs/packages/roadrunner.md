---
title: marko/roadrunner
description: RoadRunner application server driver for Marko — serves your application from one long-running PHP worker process instead of a new process per request.
---

RoadRunner application server driver for Marko — serves your application from one long-running PHP worker process instead of spawning a new PHP process per request (the PHP-FPM model). This trades the "everything resets automatically at the end of every request" guarantee of PHP-FPM for lower per-request overhead, in exchange for a set of rules about what a request handler is allowed to hold onto between requests. Read this page before deploying on RoadRunner — the failure mode when those rules are broken is a cross-user data leak, not a crash.

## Installation

```bash
composer require marko/roadrunner
composer require spiral/roadrunner-cli --dev
./vendor/bin/rr get-binary
```

`marko/roadrunner` requires `marko/core`, `marko/routing`, and `marko/config`. The `spiral/roadrunner-cli` package and `get-binary` step download the actual `rr` server binary, which is not a Composer package itself.

## Starting the Server

```bash
marko rr:serve
```

On first run, `rr:serve` writes a default `.rr.yaml` next to your project root (skipped if one already exists) and starts the server. The generated config points `server.command` at `php vendor/marko/roadrunner/worker.php` — the thin bootstrap script this package ships that boots your application once and then serves every subsequent request from the same process.

Pass `--config` to use a config file at a different path:

```bash
marko rr:serve --config=config/rr.production.yaml
```

## The Generated `.rr.yaml`

```yaml title=".rr.yaml"
version: "3"

server:
  command: "php vendor/marko/roadrunner/worker.php"
  relay: pipes
  env:
    MARKO_BASE_PATH: "/path/to/your/project"

http:
  address: 0.0.0.0:8080
  static:
    dir: public
    forbid:
      - .php
      - .htaccess
  pool:
    max_jobs: 64
    supervisor:
      max_worker_memory: 128
```

| Key | Why it's set |
| --- | --- |
| `http.static.dir: public` | Serves files under `public/` directly from RoadRunner instead of routing every asset request through PHP — the same role `.htaccess`/nginx static-file rules play in front of PHP-FPM. `.php` and `.htaccess` are explicitly forbidden from static serving so a request can never fetch application source. |
| `pool.max_jobs: 64` | Recycles each worker after 64 requests. A safety net for any per-request state that isn't cleaned up — including a leak in a third-party package this driver has no visibility into — bounding how many requests a single leaking worker can affect before it is replaced. |
| `pool.supervisor.max_worker_memory: 128` | Kills and replaces a worker once its RSS passes 128 MB. Same rationale as `max_jobs`: bound the blast radius of an undetected leak rather than let one worker grow unbounded for the life of the server. |
| `server.env.MARKO_BASE_PATH` | Tells `worker.php` where your project root is. Required because the worker script ships *inside* this package at `vendor/marko/roadrunner/worker.php`, and under a Composer path repository `vendor/marko/roadrunner` is a symlink — `__DIR__` inside the script would resolve through the symlink into the package's own source tree, not your project. See `Marko\Roadrunner\Worker\BasePathResolver`, which resolves this env var first, then the loaded Composer autoloader's own (never-symlinked) path, before falling back to the current working directory. |

Both `max_jobs` and `max_worker_memory` exist because a worker running under this driver is not disposable the way a PHP-FPM process is — see [Reset Lifecycle](#reset-lifecycle-and-the-stateful-singleton-rule) below for what the framework itself resets automatically, and why these config values remain a backstop rather than the primary defense.

## Do Not Write to STDOUT

RoadRunner's default worker relay is pipes over STDIN/STDOUT, carrying the goridge binary protocol between the worker process and the RoadRunner server. Any unstructured bytes written to STDOUT — `echo`, `print`, `var_dump()`, `print_r()`, `dd()`, an accidental top-level `<?php ?>` output, a framework error handler that echoes — corrupt that protocol stream.

This driver buffers all output produced while handling a request (`ob_start()` around every request, discarded rather than flushed) specifically so a stray `echo` cannot corrupt the relay — but the practical effect for a developer is that the output simply **vanishes**. There is no error, no warning, and nothing in the response body. If you are debugging with `var_dump()` and see nothing, this is why.

Use instead:

- A PSR logger (`Psr\Log\LoggerInterface`, e.g. via `marko/log-file`) — the worker also replaces the framework's own exception handler with one that reports through the logger, or to STDERR when no logger is bound, rather than echoing (`Marko\Roadrunner\Worker\WorkerSafeExceptionHandler`).
- `fwrite(STDERR, ...)` directly. RoadRunner captures a worker's STDERR as worker logs — it is the one stream a worker may write to freely.

## Reset Lifecycle and the Stateful Singleton Rule

**Request-scoped state held in a singleton is a cross-user leak under this worker.** PHP-FPM makes this rule invisible: every request gets a fresh process, so a singleton that caches "the current session" or "the currently authenticated user" quietly starts over each time. Under one long-running worker process, the same singleton instance serves every request, so anything it caches from request A is still sitting there when request B — a different user — is handled next, unless something explicitly clears it.

Before handling each request, `Marko\Roadrunner\Worker\WorkerRequestHandler` resets every already-resolved instance implementing `Marko\Core\Contracts\ResettableInterface`, discovered via `ContainerInterface::resolvedInstances(ResettableInterface::class)` — never a hardcoded per-package list, and never forcing a service to be constructed just to reset it. Instances are reset in a fixed, deterministic order (`ksort()`ed by container binding identifier), so reset behavior never depends on which services happened to be resolved first for a given request.

The reset runs **before** each request, not after: a request that throws, or a worker that is killed mid-request, must never be allowed to hand stale state forward into the *next* request. If a `reset()` call itself throws, that failure propagates and fails the current request with a 500 rather than being swallowed — silently continuing past a failed reset would itself be the cross-user leak this mechanism exists to prevent.

**If you write a module with a singleton that caches anything derived from the current request** (the logged-in user, session data, per-request shared view props, an open transaction, …), implement `ResettableInterface` and clear that state in `reset()`. Two worked examples fixed for exactly this reason:

- **`Session`** and **`SessionGuard`** (`marko/session-file`, `marko/authentication`) — `reset()` clears the cached session id, session data, and cached authenticated user, so the next request starts from a clean slate regardless of which user or session the previous request belonged to.
- **`Inertia::$shared`** (`marko/inertia`) — `share()` merges values into `$shared` for every subsequent `render()` call. Without a reset, data shared by one request's middleware (e.g. the current user) would still be present — and visible — in the next request's Inertia response. `reset()` clears `$shared` between requests.

`ReadWriteConnection` (`marko/database-readwrite`) also implements `ResettableInterface` for the same underlying reason, covered in more detail in [`marko/database-readwrite`'s Long-Running Processes section](/docs/packages/database-readwrite/#long-running-processes) — sticky-write routing state and any transaction left open by a request that threw before `commit()`/`rollback()` are exactly the kind of per-request state this section describes, just for a database connection rather than a session.

This driver is the only place in the monorepo that runs `packages/*/src` code under PHPStan level 6 outside `marko/core` — deliberately, since a type error here is a plausible path to a cross-user security bug rather than an ordinary bug.

For the full mechanical audit behind this section — every container singleton, boot-time binding, class static, superglobal reader, and process-global PHP setting in the monorepo, each given an explicit leak verdict — see [RoadRunner state-leak audit](/docs/packages/roadrunner-state-leaks/).

## The Session Cookie Caveat

`Marko\Session\Middleware\SessionMiddleware` only attaches a `Set-Cookie` header to the `Response` when the outgoing session id **differs** from the id the request came in with (or clears the cookie when the session becomes empty). On a repeat request with an unchanged session id, no `Set-Cookie` header is added at all.

This means the `Response` object is **not** a complete picture of session state on a repeat request — the absence of a `Set-Cookie` header does not mean the session is empty or unused, only that its id did not change during this request. Anything inspecting session state from the response alone (a test assertion, a debugging tool, custom middleware) must account for this instead of treating a missing cookie as "no session."

## What Is Not Supported

### `marko/sse`

Server-sent events stream a response for the life of the connection via `ob_end_flush()`/`flush()` — incompatible with a worker that must return control to the accept loop after each response. `Marko\Roadrunner\GuardRails\UnsafePackageChecker` refuses to boot when `marko/sse` is installed, unless the package is explicitly acknowledged:

```php title="config/roadrunner.php"
return [
    'acknowledged_unsafe_packages' => ['marko/sse'],
];
```

Acknowledging the package downgrades the boot-time refusal to a warning on STDERR — it does **not** make SSE work. `Marko\Roadrunner\Http\Psr7ResponseBridge` still throws `StreamingResponseException` per request whenever a `StreamingResponse` reaches it, so an acknowledged install still fails loudly on every SSE route rather than silently truncating the stream. Only acknowledge `marko/sse` if it is installed for a reason unrelated to this worker (e.g. served by a separate FPM pool, a transitive dependency, or a retired endpoint) — not to make SSE routes work under RoadRunner.

### `marko/debugbar`

A dev-only tool that reads `$_SERVER` directly (stale under a worker — those values are frozen at whatever the worker process started with, not per-request) and calls `ob_start()` once at boot without a matching per-request `ob_end_*()`, which then captures output from every request that follows in the same worker process. `UnsafePackageChecker` only warns for `marko/debugbar` (never refuses) — do not enable it in a worker-served production environment.

### File Uploads

`Marko\Routing\Http\Request` has no equivalent of PHP's `$_FILES`, so PSR-7 uploaded files bridged from an incoming request have nowhere to map. `Marko\Roadrunner\Http\Psr7RequestBridge` throws `UploadedFilesNotSupportedException` immediately when a request carries uploaded files, rather than silently dropping them. Do not submit `multipart/form-data` uploads to a route served through this driver.

## API Reference

### `Marko\Roadrunner\Worker\WorkerRequestHandler`

Drives the RoadRunner accept loop: resets resolved `ResettableInterface` instances, bridges the incoming PSR-7 request, routes it through the already-booted application, bridges the response back, converts any thrown exception into a 500 without leaking details outside `development`, and keeps serving.

### `Marko\Roadrunner\Worker\BasePathResolver`

Resolves the project's base path for the packaged `worker.php`, in order: the `MARKO_BASE_PATH` environment variable, then the loaded Composer autoloader's own (symlink-independent) directory, then the current working directory.

### `Marko\Roadrunner\Http\Psr7RequestBridge` / `Psr7ResponseBridge`

Convert between PSR-7 messages and `Marko\Routing\Http\Request`/`Response`. `Psr7ResponseBridge` consumes `Response::headerLines()` and uses `withAddedHeader()` for `Set-Cookie` so multiple cookies on one response are preserved rather than overwritten.

### `Marko\Roadrunner\GuardRails\UnsafePackageChecker`

| Method | Description |
| --- | --- |
| `check(): array` | Reads `ModuleRepositoryInterface`; throws `UnsafePackageException` if `marko/sse` is installed and not acknowledged via `roadrunner.acknowledged_unsafe_packages`; returns warning strings for an acknowledged `marko/sse` or an installed `marko/debugbar`. |

### `Marko\Roadrunner\Config\RrYamlTemplate`

| Method | Description |
| --- | --- |
| `render(string $basePath): string` | Renders the default `.rr.yaml` contents documented above, with `$basePath` interpolated into `server.env.MARKO_BASE_PATH`. |

### `rr:serve`

| Option | Default | Description |
| --- | --- | --- |
| `--config` | `.rr.yaml` | Path to the RoadRunner config file. Generated from `RrYamlTemplate` on first run if it doesn't already exist. |
