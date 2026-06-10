# Task 014: devai CommandRunner drains stdout and stderr concurrently (no pipe deadlock)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`CommandRunner::run()` opens a child process with separate stdout/stderr pipes and drains them sequentially: it fully reads stdout (`stream_get_contents($pipes[1])`), closes it, THEN reads stderr. A child that fills its stderr OS pipe buffer (~64KB; common for npm/build tooling) blocks on its `write()` to stderr before its stdout closes — meanwhile the parent is blocked reading stdout — producing a classic pipe deadlock where neither side makes progress. Drain both pipes concurrently (non-blocking reads driven by `stream_select`, or redirect stderr to a temp file) so a large stderr volume never deadlocks. Preserve the current return contract.

## Context
- Related files:
  - `packages/devai/src/Process/CommandRunner.php` (`run()` lines 13-32 — `proc_open` with descriptor spec `[1 => ['pipe','w'], 2 => ['pipe','w']]` at 19, sequential drain at 25-28, `proc_close` at 29, return shape `array{exitCode:int, stdout:string, stderr:string}`; `isOnPath()` 34-39 calls `run()` and must keep working)
  - `packages/devai/src/Process/CommandRunnerInterface.php` (the contract — `run()` return shape must not change)
- Patterns to follow:
  - Keep the exact return shape `['exitCode' => int, 'stdout' => string, 'stderr' => string]` and the existing `!is_resource($proc)` early return (`['exitCode' => -1, ...]`).
  - Concurrent drain: set both pipes non-blocking (`stream_set_blocking($pipe, false)`) and loop on `stream_select()` accumulating from whichever pipe is readable until both reach EOF; OR redirect stderr to a temp file via the descriptor spec (`2 => ['file', $tmp, 'w']`) and read it after `proc_close`. Either is acceptable — pick one and keep it simple.
  - Exit code must still come from `proc_close()` and be returned unchanged.
  - This package has only `DevAiInstallException`; no new exception is needed — `run()` reports failure through the return array, not a throw. Do not add a throw to the happy/large-output path.

### Verification note (read at planning time)
Confirmed against source: lines 25-28 drain stdout fully, `fclose`, then stderr — sequential. Real deadlock is timing/buffer-size dependent, so the regression test must deterministically force a >64KB stderr write while stdout is also produced.

### Testing note
A genuine deadlock is hard to assert directly. Drive it with a tiny inline `sh`/`php` child that writes well over 64KB to stderr (and some bytes to stdout), invoked through `run()`, asserting the call returns (does not hang) with both streams captured and the exit code preserved. Wrap the hang-detection with a bounded timeout so a regression fails loudly rather than blocking the suite. If the child-script approach proves environment-fragile, mark the large-stderr case `->group('integration')` and keep the captured-output + exit-code assertions as plain unit tests against a small command.

## Requirements (Test Descriptions)
- [ ] `it captures both stdout and stderr from a command`
- [ ] `it preserves the child process exit code`
- [ ] `it completes without hanging when the child writes more than 64KB to stderr`
- [ ] `it returns the proc_open failure shape when the process cannot start`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
