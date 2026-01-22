# Task 008: queue package module.php

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the module.php for the queue package with QueueConfig binding.

## Context
- Binds QueueConfig to itself for DI resolution
- Does NOT bind QueueInterface (drivers do that)
- Standard Marko module structure

## Requirements (Test Descriptions)
- [ ] `module.php exists with correct structure`
- [ ] `module.php binds QueueConfig`
- [ ] `module.php does not bind QueueInterface`

## Implementation Notes
