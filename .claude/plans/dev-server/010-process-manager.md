# Task 010: Implement ProcessManager

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create `ProcessManager` that handles starting, monitoring, and stopping subprocesses. Supports both foreground mode (combined prefixed output) and background mode (detached with PID tracking). Uses `proc_open` for process creation and `pcntl_signal` for signal handling in foreground mode.

## Context
- Related files: `packages/dev-server/src/Process/ProcessManager.php`
- In foreground mode: uses `proc_open` with pipes, reads stdout/stderr via `stream_select`, prefixes each line with `[name]` label
- In background mode: starts processes and records PIDs
- Signal handling: registers SIGINT/SIGTERM handler to stop all processes on Ctrl+C
- Must handle `proc_open` returning false (command not found, etc.) with loud error
- The Output class from core is used for writing prefixed output

## Requirements (Test Descriptions)
- [ ] `it starts a process with proc_open`
- [ ] `it stops a running process`
- [ ] `it stops all managed processes`
- [ ] `it returns process PIDs after starting`
- [ ] `it detects when a process exits unexpectedly`
- [ ] `it throws DevServerException when process fails to start`
- [ ] `it prefixes output lines with process name`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Graceful handling when pcntl extension is unavailable

## Implementation Notes
(Left blank - filled in by programmer during implementation)
