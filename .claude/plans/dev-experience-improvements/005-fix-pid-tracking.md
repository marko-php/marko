# Task 005: Fix dev:status PID tracking bug

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Fix the bug where `marko dev:status` shows all processes as "stopped" immediately after `marko up -d`. The root cause is that `proc_open()` returns the shell wrapper PID, not the actual command PID. When the shell exits, the PID becomes invalid.

## Context
- Related files: `packages/dev-server/src/Process/ProcessManager.php`, `packages/dev-server/src/Process/PidFile.php`, `packages/dev-server/tests/ProcessManagerTest.php`
- `ProcessManager::start()` uses `proc_open()` to launch processes and `proc_get_status()` to get the PID
- The PID from `proc_get_status()` is the shell wrapper process, not the actual command
- When the shell forks (e.g., `docker compose up -d`), the wrapper exits immediately and the PID goes stale
- `PidFile::isRunning()` checks via `posix_kill($pid, 0)` which correctly reports the stale PID as dead

## Requirements (Test Descriptions)
- [ ] `it captures the actual command PID not the shell wrapper PID`
- [ ] `it reports running processes as running in dev:status`
- [ ] `it reports stopped processes as stopped in dev:status`
- [ ] `it correctly tracks PID for long-running processes`

## Acceptance Criteria
- All requirements have passing tests
- `marko dev:status` correctly shows running/stopped state
- PID tracking works for all process types (PHP server, Docker, frontend, custom)
- Code follows project standards

## Implementation Notes
### The Fix: Use `exec` prefix

The simplest fix is to prepend `exec` to the shell command passed to `proc_open()`. The `exec` builtin replaces the shell process with the command, so the PID returned by `proc_get_status()` IS the actual command PID.

In `ProcessManager::start()`, change:
```php
// Old:
$process = proc_open($command, $descriptors, $pipes);

// New:
$process = proc_open("exec $command", $descriptors, $pipes);
```

**Caveat**: `exec` only works when the command is a single process. For compound commands with `&&` or pipes, `exec` won't help. But the dev-server commands are all single processes:
- `php -S localhost:8000 -t public/`
- `docker compose up -d`
- `npx tailwindcss --watch`

For the `PHP_CLI_SERVER_WORKERS=4 php -S localhost:$port -t public/` command in DevUpCommand, the env var prefix works with exec: `exec env PHP_CLI_SERVER_WORKERS=4 php -S ...` or restructure to use `proc_open()`'s `$env` parameter instead.

**Important -- scope of fix**: The `exec` prefix approach is sufficient. Do NOT refactor `ProcessManager::start()` to accept an `$env` parameter -- that changes the interface and impacts other tasks. The `exec PHP_CLI_SERVER_WORKERS=4 php -S ...` syntax works fine in sh/bash because env var prefixes are evaluated by the shell before `exec` replaces it.

**Note on Docker commands**: When Docker runs detached (`docker compose up -d`), the command exits quickly after spawning containers. The PID will still go stale because the process legitimately exits. The `exec` prefix helps for long-running processes (PHP server, frontend watchers) where the shell wrapper was the problem. Docker detached PIDs going stale is expected behavior, not a bug.

### Testing approach:
Test with actual `sleep` processes (short-lived) to verify PID tracking. The existing test helpers may need adjustment since they likely mock `proc_open`.
