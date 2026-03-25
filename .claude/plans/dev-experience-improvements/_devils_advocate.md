# Devil's Advocate Review: dev-experience-improvements

## Critical (Must fix before building)

### C1. Task 003: `context` parameter type mismatch in DevServerException (Task 003)

The implementation notes in task 003 show:

```php
throw new DevServerException(
    message: 'Cannot start PHP server: public/index.php not found.',
    context: ['path' => $indexPath],  // ARRAY
    suggestion: "Create public/index.php with:..."
);
```

But `MarkoException::__construct()` declares `context` as `private readonly string $context = ''`. Passing an array will cause a TypeError at runtime. This needs to be a string, e.g., `context: "While starting PHP development server at $indexPath"`.

### C2. Task 003: DevUpCommand is `readonly class` -- adding `ProjectPaths` requires updating test helper (Tasks 003, 004)

`DevUpCommand` is a `readonly class`. The constructor currently takes 6 parameters. Task 003 adds `ProjectPaths $paths` as a 7th. The `createDevUpCommand()` test helper in `DevUpCommandTest.php` constructs `DevUpCommand` directly and must be updated to pass a `ProjectPaths` instance. Since tasks 003 and 004 both modify the same test file independently with no dependency between them, parallel workers will produce merge conflicts.

**Fix**: Add a dependency from task 004 to task 003, or document that both tasks modify `createDevUpCommand()` and must be sequenced.

### C3. Task 002: `self.version` won't work in a `"type": "project"` skeleton (Task 002)

The skeleton's `composer.json` uses `"marko/framework": "self.version"`. The `self.version` constraint is a Composer feature for monorepo path repositories where the version is inferred from the branch. When users run `composer create-project marko/skeleton`, the skeleton is installed standalone -- `self.version` will resolve to nothing or fail because there's no monorepo context. The skeleton should use `"^0.1"` or `"*"` or whatever the current version constraint is, since it's a template for end-user projects, not a monorepo internal package.

## Important (Should fix before building)

### I1. Task 003: Guard placement must account for exception halting all processes (Task 003)

The plan says the guard goes "before the PHP server section" so Docker/frontend/etc. start first. However, when the guard throws `DevServerException`, the execute method returns via exception without ever calling `pidFile->write()` or `processManager->runForeground()`. This means:
- In detached mode: Docker/frontend processes are started but never written to the PID file, so `marko dev:down` cannot stop them.
- In foreground mode: Docker/frontend processes are started but `runForeground()` is never called, so they become orphaned.

**Fix**: Either (a) catch the exception after writing PID file and stopping started processes, or (b) move the guard before ALL process starts (simpler, since the user needs to fix index.php anyway), or (c) document that this is acceptable because in practice Docker/frontend rarely run without PHP.

### I2. Task 004: Tests that don't set `dev.detach` explicitly will break (Task 004)

The `createDevUpCommand` helper defaults to `'dev.detach' => false`. Many tests (e.g., "reads port from config", "starts Docker when docker config is true") don't override this, so they currently run in foreground mode and call `runForeground()` on the FakeProcessManager. After task 004 changes the production config default to `true`, these tests still pass because the test helper hardcodes `false` -- the tests won't actually validate the new default behavior. This is fine functionally but worth noting so the worker doesn't also change the test helper default without updating all 32 tests.

### I3. Task 005: `exec` prefix breaks compound Docker commands with `-d` flag (Task 005)

In `DevUpCommand`, when `$detach` is true, the Docker command gets ` -d` appended (line 57-58: `$dockerCommand .= ' -d'`). So the command becomes e.g., `docker compose -f compose.yaml up -d`. Prepending `exec` makes it `exec docker compose -f compose.yaml up -d`. The `docker compose up -d` command itself returns quickly (the containers run in background), so the wrapper PID issue is less relevant for Docker -- the `proc_open` shell exits right after `docker compose` returns, and the PID goes stale regardless of `exec`. The `exec` fix mainly helps long-running foreground processes like `php -S` and `npx tailwindcss --watch`.

**Fix**: Task 005 should note that `exec` is a partial fix -- it solves PID tracking for long-running processes but Docker detached commands still produce stale PIDs by design (the command exits after spawning containers). Consider whether Docker processes even belong in the PID file when run with `-d`.

### I4. Task 005: `exec` with env var prefix on PHP command (Task 005)

The PHP server command is `PHP_CLI_SERVER_WORKERS=4 php -S localhost:$port -t public/`. With `exec` prepended this becomes `exec PHP_CLI_SERVER_WORKERS=4 php -S ...`. This actually works in bash/sh because `exec` applies to the final command in the line and env var prefixes are handled by the shell. However, the task notes suggest restructuring to use `proc_open()`'s `$env` parameter. If the worker chooses that approach, it changes the `ProcessManager::start()` signature which impacts tasks 003 and 004 as well. The task should commit to one approach.

**Fix**: Specify that the simple `exec` prefix approach is sufficient and the `$env` parameter refactor is out of scope.

### I5. Task 002: Skeleton `public/index.php` uses `require_once` but docs use `require` (Tasks 001, 002)

The skeleton plan shows `require_once __DIR__ . '/../vendor/autoload.php'` but `project-structure.md` (line 114) shows `require __DIR__ . '/../vendor/autoload.php'`. These should be consistent. The Composer autoloader itself uses `require` in generated entry points.

### I6. Task 001: first-application.md uses wrong namespaces for Response classes (Task 001)

The docs reference `Marko\Http\Response`, `Marko\Http\ResponseInterface`, and `Marko\Http\JsonResponse`, but the actual class is `Marko\Routing\Http\Response`. There is no `ResponseInterface` and no `JsonResponse` in the codebase. While fixing these is arguably out of scope for "match the Application::boot() API", a worker touching these files should be told to fix or explicitly leave them. Currently the task says nothing about the controller code examples.

## Minor (Nice to address)

### M1. Task 002: Skeleton has no `database/` directory but ProjectPaths defines one

`ProjectPaths` has a `$database` property pointing to `$base/database`, but the skeleton doesn't include a `database/` directory. This is fine since not every project needs a database, but it's a minor inconsistency.

### M2. Task 007: README links to marko.build but that domain may not be live yet

The skeleton README links to `https://marko.build/docs/getting-started/first-application/`. If the domain isn't live, new users hitting these links will get 404s.

### M3. Task 005: `proc_terminate()` sends SIGTERM which may not kill child processes

In `ProcessManager::stop()`, `proc_terminate($process)` sends SIGTERM to the shell wrapper PID. Even with `exec`, if the process spawns children (e.g., PHP's `CLI_SERVER_WORKERS`), only the main process gets the signal. Worker processes may linger. This is a pre-existing issue, not introduced by this plan.

## Questions for the Team

### Q1. Should task 003's guard be moved to before ALL processes start?

The current plan starts Docker/frontend/custom processes, then checks for `public/index.php`. If it's missing, those processes are orphaned. Moving the guard to the top of `execute()` is simpler and avoids orphaned processes. Is there a real use case where someone wants Docker/frontend running but has no `public/index.php`?

### Q2. Should the skeleton include `marko/env` as a dependency?

`Application::initialize()` loads env vars via `EnvLoader` if available. The skeleton has a `.env.example` but doesn't require `marko/env`. Without it, the `.env` file is never loaded. Check if `marko/framework` metapackage already includes it.

### Q3. Should first-application.md controller examples be fixed in this plan?

The imports (`Marko\Http\Response`, `Marko\Http\ResponseInterface`, `Marko\Http\JsonResponse`) don't match the actual codebase (`Marko\Routing\Http\Response`). `JsonResponse` doesn't exist at all. Should task 001 fix these, or is that a separate issue?
