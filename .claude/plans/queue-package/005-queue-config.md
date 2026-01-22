# Task 005: QueueConfig Class

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the QueueConfig class for loading queue configuration from config/queue.php.

## Context
- Loads driver, connection, queue name, retry_after, max_attempts
- Uses marko/config for loading
- Provides typed accessors for configuration values

## Requirements (Test Descriptions)
- [ ] `QueueConfig loads driver setting`
- [ ] `QueueConfig loads connection setting`
- [ ] `QueueConfig loads queue name setting`
- [ ] `QueueConfig loads retry_after setting`
- [ ] `QueueConfig loads max_attempts setting`
- [ ] `QueueConfig provides default values`

## Implementation Notes
