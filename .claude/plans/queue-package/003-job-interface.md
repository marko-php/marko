# Task 003: JobInterface and Job Base Class

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create JobInterface contract and Job abstract base class with serialization support.

## Context
- JobInterface defines handle(), getId(), setId(), getAttempts(), incrementAttempts(), getMaxAttempts(), serialize(), unserialize()
- Job abstract class provides default implementation
- Uses PHP's native serialize/unserialize

## Requirements (Test Descriptions)
- [ ] `JobInterface defines handle method`
- [ ] `JobInterface defines id methods`
- [ ] `JobInterface defines attempt methods`
- [ ] `JobInterface defines serialization methods`
- [ ] `Job base class implements JobInterface`
- [ ] `Job serialize and unserialize work correctly`
- [ ] `Job tracks attempts correctly`

## Implementation Notes
