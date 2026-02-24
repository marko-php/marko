# Task 001: Package Scaffolding & RabbitmqConnection

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/queue-rabbitmq` package scaffolding (composer.json, Pest.php, directory structure) and the `RabbitmqConnection` class that manages RabbitMQ connections via php-amqplib. Supports both plaintext and TLS/SSL connections with lazy initialization and a protected hook for test overrides.

## Context
- Related files: `packages/queue-database/composer.json` (sibling pattern), `packages/queue-database/tests/Pest.php`
- Patterns to follow: Sibling module standards from `.claude/sibling-modules.md`
- php-amqplib provides `AMQPStreamConnection` (plaintext) and `AMQPSSLConnection` (TLS)
- Connection should be lazy: only connect on first `channel()` call
- Must have `protected createConnection()` hook so tests can override without needing a real RabbitMQ server

## Requirements (Test Descriptions)
- [x] `it creates RabbitmqConnection with default configuration`
- [x] `it creates RabbitmqConnection with custom host port user and vhost`
- [x] `it lazily connects on first channel call`
- [x] `it returns same channel on subsequent calls`
- [x] `it reports connected status correctly`
- [x] `it disconnects and clears channel reference`
- [x] `it creates SSL connection when TLS options are provided`

## Acceptance Criteria
- composer.json created with correct name, dependencies (php-amqplib/php-amqplib), autoload
- tests/Pest.php created
- RabbitmqConnection class with constructor property promotion
- Protected `createConnection()` method for testability
- All requirements have passing tests
- Code follows strict types, no final class
