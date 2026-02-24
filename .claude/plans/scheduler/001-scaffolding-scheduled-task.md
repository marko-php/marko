# Task 001: Package Scaffolding and ScheduledTask Value Object

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the scheduler package with ScheduledTask class that represents a scheduled unit of work with a cron expression.

## Context
- Namespace: `Marko\Scheduler\`
- Package: `marko/scheduler`
- Dependencies: marko/core, marko/cli
- ScheduledTask wraps a callable and a cron expression string

## Requirements (Test Descriptions)
- [ ] `it creates ScheduledTask with callable and cron expression`
- [ ] `it sets every minute frequency`
- [ ] `it sets hourly frequency`
- [ ] `it sets daily frequency`
- [ ] `it sets weekly frequency`
- [ ] `it sets custom cron expression`
- [ ] `it has marko module flag in composer.json`
