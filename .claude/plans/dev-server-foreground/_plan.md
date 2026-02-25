# Plan: Dev Server Foreground Event Loop

## Created
2026-02-25

## Status
planning

## Objective
Make `dev:up` block in foreground mode with streamed prefixed output and Ctrl+C cleanup. Add `-d`/`-p` short option support to the Input class.

## Scope

### In Scope
- Short option parsing (`-d`, `-p=8000`, `-p 8000`) in core Input class
- Foreground event loop: `stream_select()` on child process pipes, prefixed output
- Signal handling: `pcntl_signal` for SIGINT/SIGTERM to gracefully stop all processes
- DevUpCommand wiring: call foreground loop when not detached, use short options

### Out of Scope
- Colored prefixes (future enhancement)
- Option definitions on `#[Command]` attribute (larger core change)
- Changes to `dev:down` or `dev:status`

## Success Criteria
- [ ] `marko up` blocks the terminal and streams prefixed output from all child processes
- [ ] Ctrl+C gracefully stops all child processes and exits cleanly
- [ ] `marko up -d` starts processes in background, writes PID file, exits immediately
- [ ] `marko up -p 9000` overrides the port
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add short option parsing to Input | - | pending |
| 002 | Add foreground event loop to ProcessManager | - | pending |
| 003 | Wire foreground mode in DevUpCommand | 001, 002 | pending |

## Architecture Notes
- **Input short options**: Enhance `hasOption()`/`getOption()` to also match `-x` for single-char option names. No mapping system needed — the command explicitly checks both `hasOption('detach')` and `hasOption('d')`.
- **Event loop in ProcessManager**: New `runForeground()` method uses `stream_select()` on all stdout/stderr pipes, reads available data, writes via `writePrefix()`. Registers `pcntl_signal(SIGINT/SIGTERM)` handlers. On signal: `stopAll()` and return. Also exits when all processes have terminated.
- **DevUpCommand wiring**: After starting processes, call `$this->processManager->runForeground()` when not in detach mode. Check both long and short option forms.

## Risks & Mitigations
- **pcntl extension not available**: Check `function_exists('pcntl_signal')` before registering. Without it, Ctrl+C kills PHP but orphans children — acceptable degradation with warning.
- **Testing event loops**: FakeProcessManager already exists. Tests verify wiring (foreground called vs PID file written), not the actual blocking behavior.
