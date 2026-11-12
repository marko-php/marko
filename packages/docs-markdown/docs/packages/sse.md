---
title: marko/sse
description: Server-Sent Events for Marko — push real-time updates to browsers without WebSockets.
---

Server-Sent Events for Marko --- push real-time updates to browsers without WebSockets. The SSE package provides a `StreamingResponse` that controllers return in place of a standard `Response`. It handles HTTP headers, output buffering, keepalive heartbeats, and connection timeouts automatically. The browser reconnects on disconnect and sends a `Last-Event-ID` header, which your controller can use to resume from the last delivered event. Since `StreamingResponse` extends `Response`, the Router handles it without any framework changes.

## Installation

```bash
composer require marko/sse
```

## When to Use SSE vs. Broadcasting

`marko/sse` streams from inside a normal PHP request. Under PHP-FPM, **every open stream holds one FPM worker** for up to `timeout` seconds (300 by default) --- 2,000 viewers need 2,000 workers. That makes it a good fit for low-concurrency streams and a poor fit for audiences:

| Use `marko/sse` for | Use [`marko/broadcasting`](/docs/packages/broadcasting/) for |
|---|---|
| Admin dashboards, job progress, a few dozen viewers | Live updates to large audiences |
| Concurrent streams comfortably below your FPM workers minus headroom for regular traffic | Anything that could exceed `pm.max_children` |
| No extra infrastructure | A self-hosted PHP server ([`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/)), a Mercure hub (built into FrankenPHP) or a Pusher-protocol server (Pusher, Soketi, Laravel Reverb) holds the connections; publishing an event costs one pub/sub message or one short HTTP call |

Rule of thumb: keep concurrent SSE streams at or below *(FPM workers − the workers your regular traffic needs)*, and set [`max_connections`](#configuration) so a burst of viewers gets a `503` instead of exhausting the pool and taking the rest of the site down.

:::caution
`marko/sse` is not compatible with [`marko/roadrunner`](/docs/packages/roadrunner/): a long-lived stream would block the worker for every other request. RoadRunner refuses the package by default, and if acknowledged, SSE routes return a 500. Use `marko/broadcasting` instead --- [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) is the self-hosted, PHP-only option.
:::

## Configuration

The optional connection guard caps concurrent streams across **all** PHP workers. It is off by default.

```php title="config/sse.php"
use Marko\Config\Env;

return [
    // null = unlimited (guard disabled)
    'max_connections' => Env::nullableInt('SSE_MAX_CONNECTIONS', min: 1),
    // Seconds a rejected client should wait before reconnecting
    'retry_after' => Env::int('SSE_RETRY_AFTER', 5, min: 0),
];
```

When `max_connections` is set, inject `SseConnectionLimiter` and pass it to `StreamingResponse`. Before streaming, the response takes a connection slot; if none is free it sends `503 Service Unavailable` with a `Retry-After` header instead of holding a worker. The slot is released in a `finally` block --- after the stream ends, when it throws, and when the client disconnects. With `max_connections` unset, the limiter is a no-op and never touches the cache.

```php
use Marko\Routing\Attributes\Get;
use Marko\Sse\SseConnectionLimiter;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

public function __construct(
    private SseConnectionLimiter $sseConnectionLimiter,
) {}

#[Get('/jobs/{jobId}/progress')]
public function progress(int $jobId): StreamingResponse
{
    return new StreamingResponse(
        stream: new SseStream(dataProvider: fn (): array => $this->progressEvents($jobId)),
        connectionLimiter: $this->sseConnectionLimiter,
    );
}
```

Slots live in the cache bound to `CacheInterface`, so the guard needs a cache **shared across PHP processes** --- [`marko/cache-redis`](/docs/packages/cache-redis/), or [`marko/cache-file`](/docs/packages/cache-file/) on a single server. The in-process [`marko/cache-array`](/docs/packages/cache-array/) driver is refused with an `SseException`. Each slot key expires after the stream `timeout` plus 60 seconds, so a slot held by a worker that crashed frees itself.

Browsers do not retry an `EventSource` that receives a `503`; handle the `error` event and reconnect after the `Retry-After` delay:

```javascript
function connect() {
    const source = new EventSource('/jobs/1/progress');
    source.onerror = () => {
        if (source.readyState === EventSource.CLOSED) {
            setTimeout(connect, 5000);
        }
    };
}
```

## Usage

### Polling endpoint

The `dataProvider` approach polls a data source on a configurable interval (default: 1 second). This is suitable for data that doesn't need to arrive instantly, such as progress updates or periodic status checks. For real-time delivery, use the [PubSub integration](#pubsub-integration) instead.

```php
use Marko\Routing\Http\Request;
use Marko\Routing\Attributes\Get;
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

`SseStream` accepts a `Subscription` from `marko/pubsub` as an alternative to the `dataProvider` closure. Unlike the polling `dataProvider` approach, subscription mode delivers events instantly --- the stream blocks on the pub/sub channel and yields each message the moment it arrives. Messages are automatically converted to SSE events, with the channel as the event name and the payload as data. You must provide exactly one source --- either a `dataProvider` or a `subscription`, not both.

```php
use Marko\PubSub\Subscription;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

$stream = new SseStream(
    subscription: $subscription,
    timeout: 300,
);

return new StreamingResponse($stream);
```

### Deployment considerations

**PHP-FPM:** Each open SSE connection holds a worker process for the duration of the stream. Tune `pm.max_children` for your expected concurrent connections, or create a dedicated FPM pool for SSE endpoints to isolate them from regular request traffic. Set [`max_connections`](#configuration) so a burst of viewers cannot exhaust the pool, and see [When to Use SSE vs. Broadcasting](#when-to-use-sse-vs-broadcasting) for audiences larger than your worker count.

**Proxy buffering:** `StreamingResponse` sets `X-Accel-Buffering: no` automatically, which disables nginx proxy buffering so events reach the client immediately.

**Reconnection:** When the browser reconnects after a disconnect, it sends a `Last-Event-ID` header containing the last event ID it received. Read it with `$request->header('Last-Event-ID')` and pass it to your data source to resume from where the stream left off.

**Streamed response contract:** `StreamingResponse` extends routing's `AbstractStreamedResponse` and implements [`StreamedResponseInterface`](/docs/packages/routing/#file-and-streamed-responses). `Response::send()` emits the headers and calls `sendBody()`, which runs the stream through routing's `OutputStreamer` (drain output buffers, flush each event, stop when the client disconnects). Anything that checks the interface treats an SSE response as streamed: [`marko/page-cache`](/docs/packages/page-cache/) never caches it and [`marko/roadrunner`](/docs/packages/roadrunner/) refuses it (by its `text/event-stream` content type). `StreamingResponse` only overrides `send()` to take a connection slot before any header goes out, so a full server can still answer `503`.

**Middleware compatibility:** Any middleware in front of an SSE route must [decorate the response rather than rebuild it](/docs/packages/routing/#decorating-responses) --- for example `$response->withHeader(...)` instead of `new Response($response->body(), ...)`. Rebuilding discards the concrete class, so a `StreamingResponse` would be silently downgraded to a plain `Response` and the stream would never send.

### Testing heartbeats and timeouts

`SseStream` measures `heartbeatInterval` and `timeout` with a PSR-20 [`ClockInterface`](/docs/packages/clock/). Since you construct the stream yourself, the `clock` parameter defaults to a `SystemClock`. In tests, pass a [`FakeClock`](/docs/packages/testing/#fakeclock) and move it from the data provider. With `pollInterval: 0`, the stream runs without sleeping:

```php
use Marko\Sse\SseStream;
use Marko\Testing\Fake\FakeClock;

it('sends a keepalive after 15 idle seconds and closes after 20', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');

    $stream = new SseStream(
        dataProvider: function () use ($clock): array {
            $clock->travel('+5 seconds');

            return [];
        },
        heartbeatInterval: 15,
        timeout: 20,
        pollInterval: 0,
        clock: $clock,
    );

    expect(iterator_to_array($stream, preserve_keys: false))->toBe([": keepalive\n\n"]);
});
```

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

The constructor validates `event` and `id`: if either contains a CR (`\r`), LF (`\n`) or NUL (`\0`) character, `SseException::invalidField()` is thrown. SSE field values must be single-line.

Multi-line string `data` is safe: `format()` treats CRLF, a lone CR and a lone LF all as line breaks (as the SSE spec does) and writes each line as its own `data:` field, so a payload can never end the event early or inject `event`, `id` or `retry` fields.

### SseStream

```php
use Marko\Clock\SystemClock;
use Marko\Sse\SseStream;
use Marko\PubSub\Subscription;
use Psr\Clock\ClockInterface;

public function __construct(
    private ?Closure $dataProvider = null,
    private ?Subscription $subscription = null,
    private int $heartbeatInterval = 15,
    private int $timeout = 300,
    private int $pollInterval = 1,
    private ClockInterface $clock = new SystemClock(),
)

public function timeout(): int;
public function close(): void;

/** @return Generator<int, string> @throws JsonException */
public function getIterator(): Generator;
```

| Parameter | dataProvider | subscription |
|---|---|---|
| `timeout` | Yes | Yes |
| `pollInterval` | Yes | No — events arrive instantly |
| `heartbeatInterval` | Yes | Yes — keepalive emitted when no messages arrive within the interval |
| `clock` | Yes | Yes — measures `timeout` and `heartbeatInterval` |

### StreamingResponse

```php
use Marko\Sse\StreamingResponse;
use Marko\Sse\SseStream;

public const int SLOT_TTL_GRACE_SECONDS = 60;

public function __construct(
    private SseStream $stream,
    int $statusCode = 200,
    private ?SseConnectionLimiter $connectionLimiter = null,
)

/** @throws JsonException|InvalidKeyException|RandomException */
public function send(): void;     // takes a connection slot (or sends the 503), then Response::send()

/** @throws JsonException */
public function sendBody(): void; // streams the events through the OutputStreamer, then closes the stream

// From AbstractStreamedResponse: swap the OutputStreamer, e.g. to capture events in a test
public function withOutputStreamer(OutputStreamer $outputStreamer): static;

// The 503 + Retry-After response sent when no connection slot is free
public function serviceUnavailableResponse(): Response;
```

### SseConnectionLimiter

```php
use Marko\Sse\SseConnectionLimiter;

public function __construct(
    private CacheInterface $cache,
    private ?int $maxConnections,
    private int $retryAfter,
)

public function isLimited(): bool;
public function retryAfter(): int;

// Slot number, or null when every slot is taken
public function acquire(int $ttl): ?int;
public function release(int $slot): void;
```

Built from `config/sse.php` through the module's `arguments` entry. Uses only `increment()` (a return value of `1` means the slot was free) and `delete()` on the cache --- never `get()`.

### SseException

Extends [`MarkoException`](/docs/packages/core/). Includes factory methods:

- `SseException::ambiguousSource()` --- thrown when both a `dataProvider` and a `subscription` are passed to `SseStream`.
- `SseException::noSource()` --- thrown when neither a `dataProvider` nor a `subscription` is provided.
- `SseException::invalidField(string $field, string $value)` --- thrown by `SseEvent` constructor when the `event` or `id` field contains a CR (`\r`), LF (`\n`) or NUL (`\0`) character. SSE field values must not span multiple lines; passing a value with embedded newlines is an error.
- `SseException::invalidMaxConnections(int $maxConnections)` --- thrown when `sse.max_connections` is below 1.
- `SseException::processLocalCache(string $cacheClass)` --- thrown when the connection guard is enabled but `CacheInterface` is bound to a process-local driver such as `marko/cache-array`.
