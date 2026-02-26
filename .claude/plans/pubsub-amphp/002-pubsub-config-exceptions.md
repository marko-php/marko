# Task 002: Create marko/pubsub Config and Exception Classes

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add the configuration wrapper and exception classes for the pubsub package. `PubSubConfig` wraps `ConfigRepositoryInterface` for typed access to pub/sub settings. `PubSubException` follows Marko's loud-error pattern with message, context, and suggestion.

## Context
- Follow `packages/queue/src/QueueConfig.php` pattern for config wrapper
- Follow `packages/core/src/Exceptions/MarkoException.php` pattern for exceptions
- Config file at `packages/pubsub/config/pubsub.php` defines defaults
- Config keys: `pubsub.driver` (which driver to use), `pubsub.prefix` (channel prefix)
- Env vars only in config file, not in application code

## Requirements (Test Descriptions)
- [ ] `it creates PubSubConfig wrapping ConfigRepositoryInterface`
- [ ] `it reads driver from pubsub.driver config key`
- [ ] `it reads prefix from pubsub.prefix config key`
- [ ] `it provides default config file with driver and prefix values`
- [ ] `it creates PubSubException extending MarkoException with static factory methods`
- [ ] `it creates connectionFailed exception with message, context, and suggestion`
- [ ] `it creates subscriptionFailed exception with message, context, and suggestion`
- [ ] `it creates publishFailed exception with message, context, and suggestion`

## Acceptance Criteria
- All requirements have passing tests
- Config file exists at `packages/pubsub/config/pubsub.php`
- PubSubConfig uses typed getters (getString, etc.) with no fallback parameters
- All exceptions follow MarkoException three-parameter pattern
- @throws tags on all methods that throw

## Implementation Notes

### Config File
```php
// config/pubsub.php
return [
    'driver' => $_ENV['PUBSUB_DRIVER'] ?? 'redis',
    'prefix' => $_ENV['PUBSUB_PREFIX'] ?? 'marko:',
];
```

### Exception Factory Methods
```php
PubSubException::connectionFailed(string $driver, string $reason)
PubSubException::subscriptionFailed(string $channel, string $reason)
PubSubException::publishFailed(string $channel, string $reason)
PubSubException::patternSubscriptionNotSupported(string $driver)
```
