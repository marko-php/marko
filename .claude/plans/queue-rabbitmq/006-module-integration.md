# Task 006: Module Integration & Configuration

**Status**: done
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Create the module.php bindings file that registers `RabbitmqQueue` as the `QueueInterface` implementation and `RabbitmqFailedJobRepository` as the `FailedJobRepositoryInterface` implementation. Ensure the package integrates correctly with the Marko module discovery system.

## Context
- Related files: `packages/queue-database/module.php` (sibling pattern), `packages/queue-sync/module.php`
- Patterns to follow: Exact same module.php structure as sibling drivers
- The module.php maps interfaces to implementations
- Configuration for RabbitMQ connection details (host, port, credentials, TLS) comes from the application's config system
- The `extra.marko.module` flag in composer.json enables auto-discovery

## Requirements (Test Descriptions)
- [x] `it binds QueueInterface to RabbitmqQueue`
- [x] `it binds FailedJobRepositoryInterface to RabbitmqFailedJobRepository`
- [x] `it returns valid module configuration array`
- [x] `it has marko module flag in composer.json`

## Acceptance Criteria
- module.php follows exact same pattern as queue-database/module.php
- composer.json has `extra.marko.module` flag
- All requirements have passing tests
- Package can be discovered by Marko's module system
