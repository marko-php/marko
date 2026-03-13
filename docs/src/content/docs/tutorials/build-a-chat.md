---
title: Build a Real-Time Chat
description: Create a real-time chat application with Server-Sent Events, PubSub, and authentication.
---

Build a real-time chat application where messages are delivered instantly to all connected clients using Server-Sent Events and Redis PubSub.

## What You'll Build

- A real-time chat room backed by Redis PubSub
- Server-Sent Events (SSE) for instant message delivery --- no polling
- Persistent message history stored in a database
- Session-based authentication so each message has an author
- Automatic reconnection with `Last-Event-ID` to recover missed messages

## Prerequisites

- PHP 8.5+
- Composer 2.x
- Redis server running locally
- PostgreSQL (or MySQL)

## Step 1: Create the Project

```bash
composer create-project marko/skeleton my-chat
cd my-chat
composer require marko/core marko/routing marko/config marko/env \
    marko/database marko/database-pgsql \
    marko/authentication marko/session marko/session-database \
    marko/pubsub marko/pubsub-redis marko/sse
```

## Step 2: Configure Redis PubSub

```php title="config/pubsub.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'redis',
    'prefix' => 'chat:',
];
```

The prefix ensures all chat channels are namespaced under `chat:` in Redis, keeping them separate from other PubSub traffic in your application.

## Step 3: Create the Messages Table

Create a migration for persisting chat messages:

```sql title="migrations/001_create_messages.sql"
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    room VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_messages_room_id ON messages (room, id);
```

The composite index on `(room, id)` ensures efficient lookups when fetching message history and recovering missed messages after reconnection.

## Step 4: Build the Message Repository

```php title="app/chat/src/Repository/MessageRepository.php"
<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use Marko\Database\Query\QueryBuilderInterface;

class MessageRepository
{
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
    ) {}

    public function create(string $room, string $username, string $body): int
    {
        return $this->queryBuilder->table('messages')->insert([
            'room' => $room,
            'username' => $username,
            'body' => $body,
        ]);
    }

    public function forRoom(string $room, int $limit = 50): array
    {
        return $this->queryBuilder->table('messages')
            ->where('room', '=', $room)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function sinceId(string $room, int $lastId): array
    {
        return $this->queryBuilder->table('messages')
            ->where('room', '=', $room)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'ASC')
            ->get();
    }
}
```

The `sinceId` method is key for reconnection --- it fetches only messages the client missed while disconnected.

## Step 5: Build the Send Message Endpoint

When a user sends a message, the controller persists it to the database and publishes it to Redis PubSub for instant delivery to all connected SSE clients.

```php title="app/chat/src/Controller/ChatController.php"
<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\Repository\MessageRepository;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use JsonException;

#[Middleware(AuthMiddleware::class)]
class ChatController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly PublisherInterface $publisher,
        private readonly AuthManager $authManager,
    ) {}

    #[Get('/chat/{room}')]
    public function room(string $room): Response
    {
        $messages = $this->messageRepository->forRoom($room);

        return Response::json(data: [
            'room' => $room,
            'messages' => $messages,
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Post('/chat/{room}/messages')]
    public function send(string $room, Request $request): Response
    {
        $data = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        $username = (string) $this->authManager->id();

        $id = $this->messageRepository->create($room, $username, $data['body']);

        $payload = json_encode([
            'id' => $id,
            'room' => $room,
            'username' => $username,
            'body' => $data['body'],
        ], JSON_THROW_ON_ERROR);

        $this->publisher->publish(
            channel: "room.$room",
            message: new Message(channel: "room.$room", payload: $payload),
        );

        return Response::json(data: ['id' => $id], statusCode: 201);
    }
}
```

The `#[Middleware(AuthMiddleware::class)]` attribute at the class level protects every endpoint in this controller. The `PublisherInterface` is injected by the DI container --- since `marko/pubsub-redis` is installed, it resolves to the `RedisPublisher` automatically.

## Step 6: Build the SSE Streaming Endpoint

This is the core of real-time delivery. Instead of polling the database, the SSE stream subscribes to a Redis PubSub channel. Messages arrive the instant they are published --- zero delay.

```php title="app/chat/src/Controller/StreamController.php"
<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\Repository\MessageRepository;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Middleware(AuthMiddleware::class)]
class StreamController
{
    public function __construct(
        private readonly SubscriberInterface $subscriber,
        private readonly MessageRepository $messageRepository,
    ) {}

    #[Get('/chat/{room}/stream')]
    public function stream(string $room, Request $request): StreamingResponse
    {
        $lastEventId = $request->header('Last-Event-ID');

        if ($lastEventId !== null) {
            $this->replayMissed($room, (int) $lastEventId);
        }

        $subscription = $this->subscriber->subscribe("room.$room");

        $stream = new SseStream(
            subscription: $subscription,
            timeout: 300,
        );

        return new StreamingResponse(stream: $stream);
    }

    private function replayMissed(string $room, int $lastId): void
    {
        $missed = $this->messageRepository->sinceId($room, $lastId);

        foreach ($missed as $message) {
            $event = new SseEvent(
                data: $message,
                event: "room.$room",
                id: $message['id'],
            );
            echo $event->format();
            flush();
        }
    }
}
```

Key design decisions:

- **`subscription` not `dataProvider`** --- The `SseStream` accepts either a `subscription` (for real-time PubSub delivery) or a `dataProvider` closure (for polling). PubSub is the right choice for chat because messages arrive with zero latency. The `dataProvider` approach adds a `pollInterval` delay between checks.
- **`timeout: 300`** --- The stream closes after 5 minutes. The client's `EventSource` will automatically reconnect, sending `Last-Event-ID` so no messages are lost.
- **Replay on reconnect** --- Before subscribing to the live stream, `replayMissed` sends any messages the client missed during the disconnection gap.

## Step 7: Add Client-Side JavaScript

```html title="public/chat.html"
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Marko Chat</title>
    <style>
        #messages { height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 1rem; }
        .message { margin-bottom: 0.5rem; }
        .username { font-weight: bold; }
        #status { padding: 0.25rem 0; font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
    <h1>Chat Room</h1>
    <div id="status">Connecting...</div>
    <div id="messages"></div>
    <form id="send-form">
        <input type="text" id="body" placeholder="Type a message..." autocomplete="off" />
        <button type="submit">Send</button>
    </form>

    <script>
        const room = 'general';
        const messagesDiv = document.getElementById('messages');
        const statusDiv = document.getElementById('status');

        // --- SSE connection ---
        const source = new EventSource(`/chat/${room}/stream`);

        source.addEventListener(`room.${room}`, (event) => {
            const message = JSON.parse(event.data);
            appendMessage(message.username, message.body);
        });

        source.addEventListener('open', () => {
            statusDiv.textContent = 'Connected';
        });

        source.addEventListener('error', () => {
            statusDiv.textContent = 'Reconnecting...';
        });

        // --- Send messages ---
        document.getElementById('send-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('body');
            const body = input.value.trim();
            if (!body) return;

            await fetch(`/chat/${room}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });

            input.value = '';
        });

        function appendMessage(username, body) {
            const div = document.createElement('div');
            div.className = 'message';
            div.innerHTML = `<span class="username">${username}:</span> ${body}`;
            messagesDiv.appendChild(div);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    </script>
</body>
</html>
```

The `EventSource` API handles reconnection automatically. When the SSE stream closes (after the 300-second timeout or a network interruption), the browser reconnects and sends the last received event ID via the `Last-Event-ID` header. The server uses this to replay any missed messages before resuming the live stream.

Note that `source.addEventListener` uses the event name `room.general` --- this matches the `channel` field on the `Message`, which `SseStream` sets as the SSE `event` type via `SseEvent`.

## Step 8: Add Event IDs for Reliable Delivery

The streaming endpoint in Step 6 delivers raw PubSub messages. To support `Last-Event-ID` recovery, the published payload must include the database ID. The `send` method in Step 5 already includes `'id' => $id` in the JSON payload.

To surface this as an SSE event ID, create a custom stream that extracts the `id` from each message payload. Wrap the subscription in a controller that decodes the payload and emits proper `SseEvent` objects:

```php title="app/chat/src/Controller/StreamController.php"
<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\Repository\MessageRepository;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Middleware(AuthMiddleware::class)]
class StreamController
{
    public function __construct(
        private readonly SubscriberInterface $subscriber,
        private readonly MessageRepository $messageRepository,
    ) {}

    #[Get('/chat/{room}/stream')]
    public function stream(string $room, Request $request): StreamingResponse
    {
        $lastEventId = $request->header('Last-Event-ID');

        if ($lastEventId !== null) {
            $this->replayMissed($room, (int) $lastEventId);
        }

        $subscription = $this->subscriber->subscribe("room.$room");

        $stream = new SseStream(
            subscription: $subscription,
            timeout: 300,
        );

        return new StreamingResponse(stream: $stream);
    }

    private function replayMissed(string $room, int $lastId): void
    {
        $missed = $this->messageRepository->sinceId($room, $lastId);

        foreach ($missed as $message) {
            $event = new SseEvent(
                data: json_encode($message, JSON_THROW_ON_ERROR),
                event: "room.$room",
                id: $message['id'],
            );
            echo $event->format();
            flush();
        }
    }
}
```

The replay loop creates `SseEvent` objects with explicit `id` values. When the browser reconnects, `EventSource` sends the last `id` it received, and `replayMissed` fills the gap.

## Step 9: Start the Server and Test

Start the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

In separate terminals, test the flow:

```bash
# Open the SSE stream (keep running)
curl -N http://localhost:8000/chat/general/stream

# Send a message from another terminal
curl -X POST http://localhost:8000/chat/general/messages \
    -H "Content-Type: application/json" \
    -d '{"body": "Hello from Marko!"}'
```

The message should appear instantly in the SSE stream terminal --- no polling, no delay.

## What You've Learned

- Setting up Redis PubSub with [`marko/pubsub`](/docs/packages/pubsub/) and [`marko/pubsub-redis`](/docs/packages/pubsub-redis/)
- Creating an SSE stream with [`SseStream`](/docs/packages/sse/) using a PubSub `subscription` for real-time delivery
- Publishing messages through [`PublisherInterface`](/docs/packages/pubsub/) and receiving them via [`SubscriberInterface`](/docs/packages/pubsub/)
- Handling reconnection with `Last-Event-ID` and replaying missed messages from the database
- Protecting endpoints with [`AuthMiddleware`](/docs/packages/authentication/) at the class level

## Next Steps

- [Build a REST API](/docs/tutorials/build-a-rest-api/) --- add validation and token-based authentication
- [Build a Blog](/docs/tutorials/build-a-blog/) --- full CRUD application with views
- [`marko/sse`](/docs/packages/sse/) --- SSE package reference with `dataProvider` and `subscription` modes
- [`marko/pubsub`](/docs/packages/pubsub/) --- PubSub package reference with pattern subscriptions
