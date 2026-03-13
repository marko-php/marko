# marko/sse

Server-Sent Events for Marko — push real-time updates to browsers without WebSockets.

## Installation

```bash
composer require marko/sse
```

## Quick Example

```php
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

$stream = new SseStream(
    dataProvider: function () use (&$lastEventId): array {
        $messages = $this->messages->findSince($lastEventId);

        return array_map(fn ($msg) => new SseEvent(
            data: ['id' => $msg->id, 'text' => $msg->body],
            event: 'message',
            id: $msg->id,
        ), $messages);
    },
    pollInterval: 1,
    timeout: 300,
);

return new StreamingResponse($stream);
```

## Documentation

Full usage, API reference, and examples: [marko/sse](https://marko.build/docs/packages/sse/)
