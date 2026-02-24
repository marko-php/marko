# Task 002: Exchange Configuration

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the `ExchangeType` enum and `ExchangeConfig` value object that represent RabbitMQ exchange configuration. These are used by `RabbitmqQueue` to declare and manage exchanges. Supports all four standard exchange types: direct, fanout, topic, and headers.

## Context
- Related files: None yet (new package)
- Patterns to follow: Enum and value object patterns used elsewhere in codebase
- `ExchangeType` should be a backed string enum with values matching AMQP exchange type names
- `ExchangeConfig` should be a readonly class holding exchange name, type, durability, auto-delete, and arguments
- The `arguments` array is needed for headers exchange (`x-match` = `all` or `any`)

## Requirements (Test Descriptions)
- [x] `it defines all four exchange types as enum cases`
- [x] `it backs exchange types with AMQP string values`
- [x] `it creates ExchangeConfig with required name and type`
- [x] `it creates ExchangeConfig with all options including arguments`
- [x] `it defaults to durable non-auto-delete exchange`
- [x] `it provides exchange type value for AMQP declaration`

## Acceptance Criteria
- `ExchangeType` enum with cases: Direct, Fanout, Topic, Headers
- `ExchangeConfig` readonly class with constructor property promotion
- All requirements have passing tests
- Code follows strict types
