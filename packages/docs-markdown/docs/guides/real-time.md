---
title: Real-Time Events
description: Push instant updates to browsers using Server-Sent Events and PubSub.
---

Marko's SSE and PubSub packages work together to deliver real-time updates from server to browser. SSE handles the HTTP streaming connection, while PubSub provides the messaging backbone that triggers events instantly. Use this guide when you need live notifications, chat messages, dashboard updates, or any feature where users should see changes the moment they happen.

## Setup

Install the SSE package alongside a PubSub driver:

```bash
# Redis-backed (pattern subscriptions, dedicated infrastructure)
composer require marko/sse marko/pubsub-redis

# PostgreSQL-backed (zero extra infrastructure)
composer require marko/sse marko/pubsub-pgsql
```

Configure your PubSub driver with environment variables. For Redis:

```bash
PUBSUB_DRIVER=redis
PUBSUB_PREFIX=marko:
PUBSUB_REDIS_HOST=127.0.0.1
PUBSUB_REDIS_PORT=6379
```

For PostgreSQL:

```bash
PUBSUB_DRIVER=pgsql
PUBSUB_PREFIX=marko_
PUBSUB_PGSQL_HOST=127.0.0.1
PUBSUB_PGSQL_PORT=5432
PUBSUB_PGSQL_USER=app
PUBSUB_PGSQL_PASSWORD=secret
PUBSUB_PGSQL_DATABASE=app
```

## Publishing Events

When something happens in your application --- an order is placed, a message is sent, a status changes --- publish it to a channel. Inject `PublisherInterface` and send a `Message`:

```php title="app/blog/Service/CommentService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

class CommentService
{
    public function __construct(
        private PublisherInterface $publisher,
    ) {}

    public function addComment(int $postId, string $body): void
    {
        // ... persist the comment ...

        $this->publisher->publish(
            channel: "post.$postId.comments",
            message: new Message(
                channel: "post.$postId.comments",
                payload: json_encode(['postId' => $postId, 'body' => $body]),
            ),
        );
    }
}
```

## Streaming to the Browser

Create a controller that subscribes to the channel and returns a `StreamingResponse`. The `SseStream` accepts a `Subscription` directly --- it blocks on the PubSub channel and yields each message the instant it arrives, with no polling:

```php title="app/blog/Controller/CommentStreamController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

class CommentStreamController
{
    public function __construct(
        private SubscriberInterface $subscriber,
    ) {}

    #[Get('/posts/{postId}/comments/stream')]
    public function stream(int $postId): StreamingResponse
    {
        $subscription = $this->subscriber->subscribe("post.$postId.comments");

        $stream = new SseStream(
            subscription: $subscription,
            timeout: 300,
        );

        return new StreamingResponse($stream);
    }
}
```

`SseStream` converts each `Message` into an SSE event automatically --- the message's `channel` becomes the SSE event name, and the `payload` becomes the data field.

### Client-Side

Connect with the browser's built-in `EventSource` API:

```javascript
const postId = 42;
const source = new EventSource(`/posts/${postId}/comments/stream`);

source.addEventListener(`post.${postId}.comments`, (event) => {
    const comment = JSON.parse(event.data);
    appendCommentToPage(comment);
});

// The browser reconnects automatically on disconnect
```

## Polling with dataProvider

For simpler use cases that don't need instant delivery --- progress bars, periodic status checks --- use the `dataProvider` closure instead of a subscription. This polls your data source on a configurable interval:

```php
use Marko\Routing\Http\Request;
use Marko\Routing\Attributes\Get;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/jobs/{jobId}/progress')]
public function progress(Request $request, int $jobId): StreamingResponse
{
    $lastEventId = $request->header('Last-Event-ID');

    $stream = new SseStream(
        dataProvider: function () use ($jobId, &$lastEventId): array {
            $progress = $this->jobs->getProgress($jobId);

            if ($progress->updatedSince($lastEventId)) {
                $lastEventId = (string) $progress->version;

                return [new SseEvent(
                    data: ['percent' => $progress->percent],
                    event: 'progress',
                    id: $progress->version,
                )];
            }

            return [];
        },
        pollInterval: 2,
        heartbeatInterval: 15,
        timeout: 300,
    );

    return new StreamingResponse($stream);
}
```

The two modes are mutually exclusive --- `SseStream` requires exactly one source. Passing both a `dataProvider` and a `subscription` throws `SseException::ambiguousSource()`.

| | `subscription` | `dataProvider` |
|---|---|---|
| Delivery | Instant --- events arrive the moment they're published | Polled on interval |
| `pollInterval` | Not used | Controls polling frequency (default: 1s) |
| `heartbeatInterval` | Not used | Sends keepalive comments (default: 15s) |
| `timeout` | Closes stream after duration | Closes stream after duration |

## Swapping Backends

Because your application code depends on `PublisherInterface` and `SubscriberInterface` --- not concrete drivers --- switching backends is a one-line Composer change:

```bash
# Switch from Redis to PostgreSQL
composer remove marko/pubsub-redis
composer require marko/pubsub-pgsql
```

Update your environment variables to match the new driver, and your controllers and services work unchanged.

### Choosing a Backend

| Driver | Strengths | Limitations |
|---|---|---|
| `marko/pubsub-redis` | Pattern subscriptions (`psubscribe`), high throughput, dedicated pub/sub infrastructure | Requires Redis |
| `marko/pubsub-pgsql` | Zero extra infrastructure --- uses your existing database | No pattern subscriptions, payload limited to ~8KB |

## Deployment Considerations

**PHP-FPM worker pools:** Each open SSE connection holds a PHP-FPM worker for the duration of the stream. Tune `pm.max_children` to account for concurrent SSE connections, or create a dedicated FPM pool for SSE endpoints to isolate them from regular request traffic.

**Proxy buffering:** `StreamingResponse` sets `X-Accel-Buffering: no` automatically, which disables nginx proxy buffering so events reach the client immediately. If you use a different reverse proxy, ensure response buffering is disabled for SSE endpoints.

**Reconnection:** When the browser reconnects after a disconnect, it sends a `Last-Event-ID` header containing the last event ID it received. Read it with `$request->header('Last-Event-ID')` and use it to resume from where the stream left off. This is handled automatically in `dataProvider` mode; in `subscription` mode, you'll need to replay any missed messages from your data store before subscribing.

**Timeouts:** `SseStream` defaults to a 300-second (5-minute) timeout. After the timeout, the stream closes cleanly and the browser reconnects automatically. Adjust the `timeout` parameter based on your infrastructure's connection limits.

## Testing

Since `marko/testing` does not yet include PubSub or SSE fakes, test the publishing and streaming layers independently.

Test that your service publishes the correct message by mocking `PublisherInterface`:

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

it('publishes a comment event', function () {
    $publisher = Mockery::mock(PublisherInterface::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->withArgs(function (string $channel, Message $message) {
            return $channel === 'post.1.comments'
                && str_contains($message->payload, '"postId":1');
        });

    $service = new CommentService(publisher: $publisher);
    $service->addComment(postId: 1, body: 'Great post!');
});
```

Test `SseStream` output directly by creating a stream with a known subscription and iterating it:

```php
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;

it('formats events from a data provider', function () {
    $stream = new SseStream(
        dataProvider: fn (): array => [
            new SseEvent(data: ['count' => 5], event: 'update'),
        ],
        timeout: 1,
    );

    $output = '';
    foreach ($stream as $chunk) {
        $output .= $chunk;
        break; // One iteration is enough
    }

    expect($output)->toContain('event: update');
    expect($output)->toContain('data: {"count":5}');
});
```

## Related Links

- [marko/sse](/docs/packages/sse/) --- SSE package reference with full API details
- [marko/pubsub](/docs/packages/pubsub/) --- PubSub contracts and interfaces
- [marko/pubsub-redis](/docs/packages/pubsub-redis/) --- Redis driver reference
- [marko/pubsub-pgsql](/docs/packages/pubsub-pgsql/) --- PostgreSQL driver reference
