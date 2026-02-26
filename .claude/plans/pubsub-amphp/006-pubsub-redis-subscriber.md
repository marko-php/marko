# Task 006: Create marko/pubsub-redis Subscriber and Subscription

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Add the Redis subscriber and subscription classes that implement the `SubscriberInterface` and `Subscription` contracts. Uses amphp/redis `RedisSubscriber` for non-blocking channel subscription. The `RedisSubscription` wraps amphp's `RedisSubscription` iterator, transforming raw strings into `Message` value objects.

## Context
- amphp/redis `RedisSubscriber` uses a dedicated connection (separate from the publish client)
- `$subscriber->subscribe('channel')` returns `Amp\Redis\RedisSubscription` (implements `IteratorAggregate<int, string>`)
- `$subscriber->subscribeToPattern('pattern:*')` returns `RedisSubscription` (yields `[payload, channel]` array tuples for pattern matches)
- Our `RedisSubscription` wraps the amphp one, converting raw values to `Message` objects
- Channel prefixing: prepend `pubsub.prefix` to channel names on subscribe, strip on message delivery
- The `RedisSubscriber` (amphp's) needs a `RedisConnector` from the connection
- cancellation calls `unsubscribe()` on the amphp subscription

## Requirements (Test Descriptions)
- [ ] `it creates RedisSubscriber implementing SubscriberInterface`
- [ ] `it subscribes to channels with prefix applied`
- [ ] `it subscribes to patterns with prefix applied via psubscribe`
- [ ] `it creates RedisSubscription implementing Subscription interface`
- [ ] `it iterates messages as Message value objects with channel and payload`
- [ ] `it strips prefix from channel name in received messages`
- [ ] `it cancels subscription via cancel method`

## Acceptance Criteria
- All requirements have passing tests
- RedisSubscription properly wraps amphp iterator → Message conversion
- Pattern subscriptions populate Message.pattern field
- Channel subscriptions have Message.pattern as null
- cancel() delegates to amphp RedisSubscription::unsubscribe()
- All sibling conventions followed (matches pgsql subscriber naming/visibility)

## Implementation Notes

### File Structure
```
packages/pubsub-redis/
  src/
    Driver/
      RedisPublisher.php       (from task 005)
      RedisSubscriber.php      (new)
      RedisSubscription.php    (new)
  tests/
    Driver/
      RedisSubscriberTest.php  (new)
      RedisSubscriptionTest.php (new)
```

### Subscriber Design
```php
class RedisSubscriber implements SubscriberInterface
{
    public function __construct(
        private RedisPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function subscribe(string ...$channels): Subscription
    {
        // Prefix each channel, create amphp RedisSubscriber, subscribe to each
        // Return wrapped RedisSubscription
    }

    public function psubscribe(string ...$patterns): Subscription
    {
        // Prefix each pattern, use subscribeToPattern on amphp subscriber
        // Return wrapped RedisSubscription with pattern info
    }
}
```

### Subscription Iterator Transformation

For channel subscriptions, amphp yields `string` (the payload):
```php
foreach ($amphpSubscription as $payload) {
    yield new Message(channel: $channel, payload: $payload);
}
```

For pattern subscriptions, amphp yields `[string $payload, string $matchedChannel]`:
```php
foreach ($amphpSubscription as [$payload, $matchedChannel]) {
    yield new Message(channel: $matchedChannel, payload: $payload, pattern: $pattern);
}
```
