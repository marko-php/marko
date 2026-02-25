# Task 002: Add foreground event loop to ProcessManager

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add a `runForeground()` method to `ProcessManager` that blocks and streams output from all managed child processes. Uses `stream_select()` to multiplex stdout/stderr from all processes, writes prefixed lines via `writePrefix()`, handles SIGINT/SIGTERM for graceful shutdown, and exits when all processes terminate.

## Context
- Related files: `packages/dev-server/src/Process/ProcessManager.php`, `packages/dev-server/tests/Process/ProcessManagerTest.php`
- ProcessManager already stores pipes (stdout/stderr) as non-blocking streams in `$this->processes[$name]['pipes']`
- `writePrefix(string $name, string $line)` already exists and writes `[$name] $line` to Output
- `stopAll()` already exists for cleanup
- `isRunning(string $name)` checks `proc_get_status`
- Use `stream_select()` with a timeout (e.g., 200ms) to allow periodic signal dispatch and process liveness checks
- Call `pcntl_signal_dispatch()` each iteration if pcntl is available
- Register `pcntl_signal(SIGINT, ...)` and `pcntl_signal(SIGTERM, ...)` before the loop
- If pcntl is not available, the loop still works but Ctrl+C will abruptly kill PHP (acceptable)
- The loop ends when: (a) signal received → stopAll() and return, or (b) all processes have exited → return
- Read data in chunks, split by newlines, buffer partial lines

## Requirements (Test Descriptions)
- [ ] `it reads stdout from managed processes`
- [ ] `it reads stderr from managed processes`
- [ ] `it writes prefixed output for each process`
- [ ] `it stops all processes on signal`
- [ ] `it exits when all processes terminate`
- [ ] `it works without pcntl extension`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No busy-waiting (uses stream_select with timeout)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
