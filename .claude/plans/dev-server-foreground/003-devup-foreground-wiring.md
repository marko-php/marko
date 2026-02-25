# Task 003: Wire foreground mode in DevUpCommand

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Update `DevUpCommand` to call `processManager->runForeground()` when not in detach mode, making the command block and stream output until Ctrl+C. Also add `-d` and `-p` short option support alongside the existing `--detach` and `--port` long options.

## Context
- Related files: `packages/dev-server/src/Command/DevUpCommand.php`, `packages/dev-server/tests/Command/DevUpCommandTest.php`
- Current execute() starts processes and returns 0 immediately in both modes
- For detach: existing behavior is correct (write PID file, print summary, exit)
- For foreground: after starting processes, call `$this->processManager->runForeground()` which blocks
- Short options: `$input->hasOption('d')` for detach, `$input->getOption('p')` for port
- FakeProcessManager in tests needs a `runForeground()` method (record that it was called)
- The "Press Ctrl+C to stop" message should only appear in foreground mode (already correct)

## Requirements (Test Descriptions)
- [ ] `it calls runForeground when not detached`
- [ ] `it does not call runForeground when detached`
- [ ] `it accepts -d as shorthand for --detach`
- [ ] `it accepts -p as shorthand for --port`
- [ ] `it writes PID file only in detach mode`

## Acceptance Criteria
- All requirements have passing tests
- Existing DevUpCommand tests still pass
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
