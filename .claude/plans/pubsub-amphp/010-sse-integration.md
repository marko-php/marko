# Task 010: SSE Integration — SseStream Subscription Support

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Modify the existing `marko/sse` package to support consuming a `Subscription` directly. Currently `SseStream` uses a polling `$dataProvider` closure with `sleep()`. Add an alternative constructor path that accepts a `Subscription` (from marko/pubsub) and converts each `Message` to an `SseEvent`, streaming them without polling.

## Context
- Existing files to modify:
  - `packages/sse/src/SseStream.php` — Add subscription support
  - `packages/sse/composer.json` — Add `marko/pubsub` as optional dependency (suggest, not require)
- Current `SseStream` constructor: `(Closure $dataProvider, int $heartbeatInterval = 15, int $timeout = 300, int $pollInterval = 1)`
- New: Accept either `dataProvider` OR `subscription`, not both
- When using subscription mode: iterate the subscription directly (no sleep/poll), still support heartbeat and timeout
- The `Subscription` is an `IteratorAggregate<int, Message>` — each `Message` becomes an `SseEvent`
- The `marko/sse` package should NOT require `marko/pubsub` — it should work without it. Use a nullable parameter.
- `SseStream` cannot be readonly class because it would need to hold mutable state for heartbeat tracking

## Requirements (Test Descriptions)
- [ ] `it creates SseStream with subscription parameter`
- [ ] `it iterates subscription messages as formatted SSE events`
- [ ] `it converts Message payload to SseEvent data field`
- [ ] `it uses Message channel as SseEvent event field`
- [ ] `it maintains existing dataProvider behavior unchanged`
- [ ] `it throws SseException when both dataProvider and subscription are provided`
- [ ] `it throws SseException when neither dataProvider nor subscription is provided`

## Acceptance Criteria
- All requirements have passing tests
- Existing tests still pass (no regression)
- `marko/pubsub` is NOT a hard dependency (suggested only)
- SseStream works in both modes: polling (existing) and subscription (new)
- Heartbeat and timeout still work in subscription mode

## Implementation Notes

### Modified SseStream Constructor
```php
readonly class SseStream implements IteratorAggregate
{
    public function __construct(
        private ?Closure $dataProvider = null,
        private ?Subscription $subscription = null,
        private int $heartbeatInterval = 15,
        private int $timeout = 300,
        private int $pollInterval = 1,
    ) {
        if ($dataProvider !== null && $subscription !== null) {
            throw SseException::ambiguousSource();
        }
        if ($dataProvider === null && $subscription === null) {
            throw SseException::noSource();
        }
    }
}
```

Note: `SseStream` is currently `readonly class`. Since `Subscription` is nullable and set at construction, it can remain readonly.

### Subscription-Mode Iterator
```php
public function getIterator(): Generator
{
    if ($this->subscription !== null) {
        yield from $this->iterateSubscription();
        return;
    }
    yield from $this->iterateDataProvider();
}

private function iterateSubscription(): Generator
{
    $startTime = time();

    foreach ($this->subscription as $message) {
        if ((time() - $startTime) >= $this->timeout) {
            return;
        }

        $event = new SseEvent(
            data: $message->payload,
            event: $message->channel,
        );
        yield $event->format();
    }
}
```

### composer.json Change
```json
{
    "suggest": {
        "marko/pubsub": "For real-time pub/sub-driven SSE streams"
    }
}
```

### Import
Since `Subscription` may not be installed, the type hint uses the interface. If marko/pubsub is not installed, passing a subscription will fail at autoload time — which is correct behavior (they need to install the package).
