# Task 002: NotificationManager

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement the NotificationManager that serves as a registry and resolver for notification channels. It stores named channels (e.g., 'mail', 'database') and provides lookup by name, throwing loud errors when requesting unregistered channels.

## Context
- Namespace: `Marko\Notification\`
- Location: `src/NotificationManager.php`
- Pattern: Similar to a service registry -- channels are registered at boot time and resolved by name at send time
- The manager does NOT send notifications itself; it only provides channel resolution. NotificationSender handles dispatching.

### Class Design
```php
class NotificationManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    public function register(string $name, ChannelInterface $channel): void;
    public function channel(string $name): ChannelInterface;
    public function hasChannel(string $name): bool;
    public function getRegisteredChannels(): array;
}
```

### Error Handling
- `channel()` throws `NotificationException::unknownChannel()` when name is not registered
- Registration is idempotent -- re-registering the same name replaces the previous channel

## Requirements (Test Descriptions)
- [ ] `it registers a channel by name`
- [ ] `it resolves a registered channel by name`
- [ ] `it throws NotificationException for unknown channel name`
- [ ] `it reports whether a channel is registered via hasChannel`
- [ ] `it returns all registered channel names`
- [ ] `it replaces channel when registering same name twice`

## Acceptance Criteria
- NotificationManager stores channels keyed by string name
- `channel()` returns the ChannelInterface for a given name or throws NotificationException::unknownChannel()
- `hasChannel()` returns bool for existence check
- `getRegisteredChannels()` returns array of registered channel name strings
- No constructor dependencies (channels registered via boot callback)
- Strict types, no final class

## Implementation Notes
(Left blank)
