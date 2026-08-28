# Task 008: rr:serve Command and .rr.yaml Scaffolding

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add an `rr:serve` CLI command and ship a working `.rr.yaml` so starting a Marko app under RoadRunner is one command rather than a research project.

## Context
- Follow the `#[Command]` attribute pattern used across the monorepo; see any `packages/*/src/Command*/` directory for the shape, and `packages/core/src/Command/CommandInterface.php` for the contract.
- Command namespacing is by convention (`db:migrate`, `route:list`, `cache:clear`), so `rr:serve` fits.
- The shipped `.rr.yaml` must point at `vendor/marko/roadrunner/worker.php` — the worker ships inside the package and is not published into the project (decided).
- If the RoadRunner binary is missing, fail loudly with installation guidance rather than a raw "command not found" — framework principle #1.
- Consider whether `marko/devserver` should know about this (it manages `dev:up`/`dev:down` processes via `ProcessManager`). Integrating is NOT required by this task; if it looks worthwhile, note it for a follow-up issue rather than expanding scope here.
- This task shells out to a binary and writes a config file; it does not consume the worker classes. Depending on 001 alone is correct — the only contract it shares with task 004 is the `vendor/marko/roadrunner/worker.php` path, which is fixed by decision, not by code.

### The shipped `.rr.yaml` must actually work end to end

A config that boots the worker but serves a broken app is worse than no config. The default must include:

- `version: "3"` and `server.command: "php vendor/marko/roadrunner/worker.php"`, `server.relay: pipes`.
- `server.env.MARKO_BASE_PATH` — task 004 resolves the application root from this first, because walking up from `__DIR__` breaks under Composer path-repository symlinks.
- **`http.static.dir: public`** (plus `http.static.forbid`). Without it every CSS, JS and image request 404s, because RoadRunner does not serve static files unless told to. The docs walkthrough in task 010 is not reproducible without this.
- **`pool.max_jobs`** — recycle each worker after N requests. This is the safety net for any leak the task 005 spike did not find, and it is the single cheapest piece of production insurance in the whole plan. Pick a conservative default and comment *why* it is there.
- **`pool.supervisor.max_worker_memory`** — kill and replace a worker that grows past a memory ceiling. Same rationale.
- `pool.num_workers` left unset so RoadRunner defaults to CPU count.

### Command behaviour

- `rr:serve` must not silently swallow RoadRunner's stdout/stderr — the operator needs the server log.
- Passing a custom config path through is required; the default is `.rr.yaml` in the project root.
- Do not overwrite an existing `.rr.yaml`. Scaffolding must be explicit and refuse to clobber, reporting the existing file.

## Requirements (Test Descriptions)
- [x] `it registers an rr serve command`
- [x] `it fails with installation guidance when the roadrunner binary is missing`
- [x] `it ships a default rr yaml pointing at the packaged worker`
- [x] `it configures static file serving from the public directory`
- [x] `it configures a max jobs worker recycle limit`
- [x] `it configures a worker memory ceiling`
- [x] `it passes the base path to the worker through the server environment`
- [x] `it passes a custom config path through to roadrunner when given one`
- [x] `it refuses to overwrite an existing rr yaml`

## Acceptance Criteria
- All requirements have passing tests
- Command follows the `#[Command]` attribute convention
- The shipped `.rr.yaml` is valid RoadRunner v3 configuration
- Code follows code standards

## Implementation Notes

- `Marko\Roadrunner\Command\ServeCommand` (`rr:serve`) depends on `BinaryLocatorInterface` and `ProcessRunnerInterface` (both interfaces, bound to defaults in the new `packages/roadrunner/module.php`) plus `ProjectPaths`, so tests can substitute fakes without touching a real `rr` binary or spawning a real process — this is what satisfies "do not require a RoadRunner binary to be installed for your tests to pass."
- Default `BinaryLocator` checks `<base>/rr`, `<base>/vendor/bin/rr`, then `command -v rr` on PATH. Default `ProcessRunner` runs via `proc_open` with descriptors `[STDIN, STDOUT, STDERR]` (not pipes), so RoadRunner's log streams straight through and is never buffered/swallowed.
- `RrYamlTemplate::render(string $basePath)` is a pure static renderer (typed consts `MAX_JOBS = 64`, `MAX_WORKER_MEMORY_MB = 128`, each with an inline comment explaining the safety-net rationale, per the task). `ServeCommand::execute()` writes this template to the resolved config path only when the file does not already exist; if it exists, the command reports it via `Output::writeLine` and leaves it untouched, then proceeds straight to `rr serve -c <path>` — this is the "one command to start" flow described in the task context: a second `rr:serve` run on an already-scaffolded project reuses the existing config rather than erroring.
- Config path resolution: `--config=<path>` (relative to project base, or absolute if it starts with `/`), default `.rr.yaml` in the project root.
- Requirements 2 ("binary missing"), 3 ("ships default yaml"), 4 ("static dir"), 5 ("max jobs"), 6 ("memory ceiling"), 7 ("base path env"), and 8 ("custom config path") all passed immediately on their first RED run because `ServeCommand` and `RrYamlTemplate` were written as a single cohesive unit before the first test — the constructor's dependencies and the template's full content were needed together for requirement 1 to even compile meaningfully. Noted as over-implementation relative to strict one-test-at-a-time TDD; each requirement's test was still written and verified passing before being marked `[x]`.
- Follow-up for a separate issue (not implemented here, out of scope per task note): `marko/devserver`'s `dev:up`/`ProcessManager` could optionally manage `rr serve` as one of its supervised dev processes, similar to how it manages Docker/frontend/pubsub. Left as a future integration since this task's `rr:serve` already works standalone.
- Full local verification: `packages/roadrunner` package suite (46 tests) green, `phpcs` clean on `packages/roadrunner`, `phpstan` level 6 clean on `packages/roadrunner/src`, full monorepo `composer test` equivalent run (7027 passed, 0 failed, pre-existing 1 warning/36 risky/157 notices/2 skipped unrelated to this change).
