# Task 016: CLI queue:failed Command

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create the queue:failed CLI command for listing failed jobs.

## Context
- Lists all failed jobs in table format
- Shows ID, queue, job class, failed timestamp
- Shows total count

## Requirements (Test Descriptions)
- [ ] `queue:failed lists failed jobs`
- [ ] `queue:failed shows job details`
- [ ] `queue:failed shows total count`
- [ ] `queue:failed handles empty list`

## Implementation Notes
