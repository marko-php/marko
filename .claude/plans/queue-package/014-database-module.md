# Task 014: queue-database module.php

**Status**: pending
**Depends on**: 011, 012, 013
**Retry count**: 0

## Description
Create the module.php for queue-database package with driver bindings.

## Context
- Binds QueueInterface via DatabaseQueueFactory
- Binds FailedJobRepositoryInterface to DatabaseFailedJobRepository
- Requires marko/database for ConnectionInterface

## Requirements (Test Descriptions)
- [ ] `module.php exists with correct structure`
- [ ] `module.php binds QueueInterface via factory`
- [ ] `module.php binds FailedJobRepositoryInterface`

## Implementation Notes
