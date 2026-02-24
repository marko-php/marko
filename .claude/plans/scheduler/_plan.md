# Plan: scheduler Package

## Created
2026-02-23

## Status
completed

## Objective
Build `marko/scheduler` — task scheduling system for running recurring jobs via cron-like expressions.

## Scope
### In Scope
- Schedule class for defining scheduled tasks
- ScheduledTask with frequency methods (everyMinute, hourly, daily, weekly, cron)
- RunScheduleCommand CLI command
- Due task detection based on current time

### Out of Scope
- Queue integration (tasks run synchronously for now)
- Overlapping prevention
- Timezone support
- Event hooks (before/after task)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding and ScheduledTask value object | - | completed |
| 002 | Schedule implementation with frequency methods | 001 | completed |
| 003 | RunScheduleCommand and due task detection | 002 | completed |

## Architecture Notes
- Schedule holds array of ScheduledTask instances
- ScheduledTask wraps a callable + cron expression
- Frequency methods set cron expression: everyMinute('* * * * *'), hourly('0 * * * *'), etc.
- isDue() checks if cron expression matches current time
- RunScheduleCommand iterates due tasks and executes them
- Cron parsing: simple pattern matching (minute hour day month weekday)
