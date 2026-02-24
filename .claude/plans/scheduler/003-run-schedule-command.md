# Task 003: RunScheduleCommand and Integration

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Implement the CLI command that runs due scheduled tasks. This is invoked by system cron every minute: `* * * * * marko schedule:run`.

## Context
- Command: schedule:run
- Gets Schedule from container, calls dueTasksAt(now)
- Executes each due task's callable
- Reports which tasks ran and their results
- Reference: packages/cache/src/Command/CacheCommand.php (CLI command pattern)
- Reference: packages/session/src/Command/GarbageCollectCommand.php (CLI command pattern)

## Requirements (Test Descriptions)
- [ ] `it creates RunScheduleCommand with schedule dependency`
- [ ] `it executes due tasks`
- [ ] `it skips non-due tasks`
- [ ] `it reports executed task count`
- [ ] `it handles task execution errors gracefully`
