# Task 007: WorkerInterface and Worker Implementation

**Status**: pending
**Depends on**: 003, 004, 006
**Retry count**: 0

## Description
Create WorkerInterface contract and Worker implementation for processing jobs.

## Context
- WorkerInterface defines work() and stop() methods
- Worker processes jobs with configurable retry attempts
- Handles failed jobs with exponential backoff
- Supports --once flag for single job processing

## Requirements (Test Descriptions)
- [ ] `WorkerInterface defines work method`
- [ ] `WorkerInterface defines stop method`
- [ ] `Worker processes jobs from queue`
- [ ] `Worker handles job failures with retry`
- [ ] `Worker stores failed job after max attempts`
- [ ] `Worker stops when stop() is called`
- [ ] `Worker processes single job with once flag`

## Implementation Notes
