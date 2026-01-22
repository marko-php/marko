# Task 006: FailedJobRepositoryInterface

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create FailedJobRepositoryInterface contract and FailedJob value object.

## Context
- FailedJob is a value object with id, queue, payload, exception, failedAt
- FailedJobRepositoryInterface defines store, all, find, delete, clear, count methods
- Used for storing and retrieving failed jobs for retry

## Requirements (Test Descriptions)
- [ ] `FailedJob stores all properties correctly`
- [ ] `FailedJob is readonly`
- [ ] `FailedJobRepositoryInterface defines store method`
- [ ] `FailedJobRepositoryInterface defines all method`
- [ ] `FailedJobRepositoryInterface defines find method`
- [ ] `FailedJobRepositoryInterface defines delete method`
- [ ] `FailedJobRepositoryInterface defines clear method`
- [ ] `FailedJobRepositoryInterface defines count method`

## Implementation Notes
