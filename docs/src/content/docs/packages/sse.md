---
title: marko/sse
description: Server-Sent Events for Marko — push real-time updates to browsers without WebSockets.
---

Server-Sent Events for Marko --- push real-time updates to browsers without WebSockets. The SSE package provides a `StreamingResponse` that controllers return in place of a standard `Response`. It handles HTTP headers, output buffering, keepalive heartbeats, and connection timeouts automatically. The browser reconnects on disconnect and sends a `Last-Event-ID` header, which your controller can use to resume from the last delivered event. Since `StreamingResponse` extends `Response`, the Router handles it without any framework changes.

## Installation

```bash
composer require marko/sse
```

## Usage

### Basic streaming endpoint

Return a `StreamingResponse` from any controller action. The `dataProvider` closure is called on each poll interval and should return an array of `SseEvent` objects.

```php
use Marko\Routing\Http\Request;
use Marko\Routing\Route\Get;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/spaces/{spaceId}/stream')]
public function stream(Request $request, int $spaceId): StreamingResponse
{
    $lastEventId = $request->header('Last-Event-ID');

    $stream = new SseStream(
        dataProvider: function () use ($spaceId, &$lastEventId): array {
            $messages = $this->messages->findSince($spaceId, $lastEventId);
            $events = [];

            foreach ($messages as $message) {
                $lastEventId = (string) $message->id;
                $events[] = new SseEvent(
                    data: ['id' => $message->id, 'text' => $message->body],
                    event: 'message',
                    id: $message->id,
                );
            }

            return $events;
        },
        pollInterval: 1,
        heartbeatInterval: 15,
        timeout: 300,
    );

    return new StreamingResponse($stream);
}
```

### Client-side

```javascript
const source = new EventSource('/spaces/1/stream');

source.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    appendMessageToChat(message);
});

// The browser sends Last-Event-ID automatically on reconnect
```

### Named events and reconnection

Use the `event` parameter on `SseEvent` to distinguish message types on the client. Set `id` to enable browser reconnection with `Last-Event-ID`:

```php
use Marko\Sse\SseEvent;

new SseEvent(
    data: ['type' => 'status', 'online' => true],
    event: 'presence',
    id: $cursor,
);
```

On the client, listen by event name:

```javascript
source.addEventListener('presence', (event) => {
    const status = JSON.parse(event.data);
    updatePresenceIndicator(status);
});
```

### Retry interval

Tell the browser how long to wait before reconnecting after a disconnect:

```php
use Marko\Sse\SseEvent;

new SseEvent(
    data: 'connected',
    retry: 3000, // milliseconds
);
```

### PubSub integration

`SseStream` accepts a `Subscription` from `marko/pubsub` as an alternative to the `dataProvider` closure. Messages from the subscription are automatically converted to SSE events, with the channel as the event name and the payload as data. You must provide exactly one source --- either a `dataProvider` or a `subscription`, not both.

```php
use Marko\PubSub\Subscription;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

$stream = new SseStream(
    subscription: $subscription,
    heartbeatInterval: 15,
    timeout: 300,
);

return new StreamingResponse($stream);
```

### Deployment considerations

**PHP-FPM:** Each open SSE connection holds a worker process for the duration of the stream. Tune `pm.max_children` for your expected concurrent connections, or create a dedicated FPM pool for SSE endpoints to isolate them from regular request traffic.

**Proxy buffering:** `StreamingResponse` sets `X-Accel-Buffering: no` automatically, which disables nginx proxy buffering so events reach the client immediately.

**Reconnection:** When the browser reconnects after a disconnect, it sends a `Last-Event-ID` header containing the last event ID it received. Read it with `$request->header('Last-Event-ID')` and pass it to your data source to resume from where the stream left off.

## API Reference

### SseEvent

```php
use Marko\Sse\SseEvent;

public function __construct(
    public string|array $data,
    public ?string $event = null,
    public string|int|null $id = null,
    public ?int $retry = null,
)

/** @throws JsonException */
public function format(): string;
```

### SseStream

```php
use Marko\Sse\SseStream;
use Marko\PubSub\Subscription;

public function __construct(
    private ?Closure $dataProvider = null,
    private ?Subscription $subscription = null,
    private int $heartbeatInterval = 15,
    private int $timeout = 300,
    private int $pollInterval = 1,
)

public function close(): void;

/** @return Generator<int, string> @throws JsonException */
public function getIterator(): Generator;
```

### StreamingResponse

```php
use Marko\Sse\StreamingResponse;
use Marko\Sse\SseStream;

public function __construct(private SseStream $stream, int $statusCode = 200)

/** @throws JsonException */
public function send(): void;
```

### SseException

Extends [`MarkoException`](/docs/packages/core/). Throw for domain-specific SSE error conditions. Includes two factory methods:

- `SseException::ambiguousSource()` --- thrown when both a `dataProvider` and a `subscription` are passed to `SseStream`.
- `SseException::noSource()` --- thrown when neither a `dataProvider` nor a `subscription` is provided.
