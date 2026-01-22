# Task 017: CLI queue:retry Command

**Status**: completed
**Depends on**: 006, 007
**Retry count**: 0

## Description
Create the queue:retry CLI command for retrying failed jobs.

## Context
- Retries specific failed job by ID
- Supports --all flag to retry all failed jobs
- Removes from failed_jobs and pushes back to queue

## Requirements (Test Descriptions)
- [ ] `queue:retry retries specific job by ID`
- [ ] `queue:retry supports all flag`
- [ ] `queue:retry shows success message`
- [ ] `queue:retry handles invalid ID`

## Implementation Notes
