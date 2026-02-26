# Task 008: Create marko/pubsub-pgsql Subscriber and Subscription

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Add the PostgreSQL subscriber and subscription classes that implement `SubscriberInterface` and `Subscription`. Uses amphp/postgres `PostgresConnection::listen()` for non-blocking LISTEN. The `PgSqlSubscription` wraps amphp's `PostgresListener` iterator, converting `PostgresNotification` objects to `Message` value objects.

## Context
- amphp/postgres `$connection->listen('channel')` returns `PostgresListener` (implements `Traversable<int, PostgresNotification>`)
- `PostgresNotification` has `->channel`, `->pid`, `->payload`
- LISTEN uses a dedicated persistent connection (amphp pool reserves one connection for all listeners)
- For multiple channels, call `listen()` once per channel, merge the iterators
- **Pattern subscriptions (psubscribe)**: PostgreSQL LISTEN does not support glob patterns natively. The `psubscribe()` method should throw `PubSubException::patternSubscriptionNotSupported('pgsql')` — this is an explicit, loud error telling users to use Redis if they need pattern subscriptions
- Channel prefix is applied/stripped just like Redis
- The subscriber needs a dedicated connection separate from the publisher's connection

## Requirements (Test Descriptions)
- [ ] `it creates PgSqlSubscriber implementing SubscriberInterface`
- [ ] `it subscribes to channels with prefix applied via LISTEN`
- [ ] `it throws PubSubException for psubscribe since Postgres does not support pattern subscriptions`
- [ ] `it creates PgSqlSubscription implementing Subscription interface`
- [ ] `it iterates notifications as Message value objects with channel and payload`
- [ ] `it strips prefix from channel name in received messages`
- [ ] `it cancels subscription by calling unlisten on all PostgresListeners`

## Acceptance Criteria
- All requirements have passing tests
- PgSqlSubscription converts `PostgresNotification` → `Message`
- psubscribe throws loud error with helpful suggestion (use Redis driver)
- Multi-channel subscription merges multiple PostgresListener iterators into single stream
- cancel() calls unlisten() on each active listener
- Sibling consistency with pubsub-redis subscriber (same method signatures, naming, visibility)

## Implementation Notes

### File Structure
```
packages/pubsub-pgsql/
  src/
    Driver/
      PgSqlPublisher.php       (from task 007)
      PgSqlSubscriber.php      (new)
      PgSqlSubscription.php    (new)
  tests/
    Driver/
      PgSqlSubscriberTest.php  (new)
      PgSqlSubscriptionTest.php (new)
```

### Subscriber Design
```php
class PgSqlSubscriber implements SubscriberInterface
{
    public function __construct(
        private PgSqlPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function subscribe(string ...$channels): Subscription
    {
        $listeners = [];
        $conn = $this->connection->connection();
        foreach ($channels as $channel) {
            $prefixed = $this->config->prefix() . $channel;
            $listeners[] = $conn->listen($prefixed);
        }
        return new PgSqlSubscription($listeners, $this->config->prefix());
    }

    /**
     * @throws PubSubException
     */
    public function psubscribe(string ...$patterns): Subscription
    {
        throw PubSubException::patternSubscriptionNotSupported('pgsql');
    }
}
```

### Subscription Iterator
```php
// Merge multiple PostgresListeners into single Message stream
public function getIterator(): Generator
{
    // For single listener: straightforward iteration
    // For multiple: use Amp\async + Pipeline to merge
    foreach ($this->listeners as $listener) {
        foreach ($listener as $notification) {
            $channel = $this->stripPrefix($notification->channel);
            yield new Message(channel: $channel, payload: $notification->payload);
        }
    }
}
```

Note: For multiple channels, a simple sequential iteration won't work with async (one listener blocks the others). The implementation should use amphp's async combinators to merge multiple listener iterables concurrently. Consider using `Amp\Pipeline\Pipeline::merge()` or spinning up concurrent iterators.

### amphp/postgres API
```php
$connection = Amp\Postgres\connect($config);
$listener = $connection->listen('prefixed_channel');
foreach ($listener as $notification) {
    // $notification->channel, $notification->pid, $notification->payload
}
$listener->unlisten(); // stop listening
```
