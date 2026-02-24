# Task 002: Schedule Implementation with Frequency Methods

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement the Schedule class that collects ScheduledTask instances and determines which are due to run.

## Context
- Schedule::call(callable): ScheduledTask - fluent API to add tasks
- Schedule::dueTasksAt(DateTimeInterface): array - returns tasks due at given time
- isDue on ScheduledTask checks cron expression against time
- Cron format: minute hour day-of-month month day-of-week

## Requirements (Test Descriptions)
- [ ] `it adds task via call method`
- [ ] `it returns ScheduledTask for fluent configuration`
- [ ] `it finds due tasks at given time`
- [ ] `it excludes non-due tasks`
- [ ] `it detects every minute task as always due`
- [ ] `it detects hourly task due at top of hour`
- [ ] `it detects daily task due at midnight`
- [ ] `it matches wildcard cron fields`
- [ ] `it matches specific cron field values`
