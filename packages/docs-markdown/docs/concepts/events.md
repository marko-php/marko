---
title: Events & Observers
description: Decouple your code with event-driven architecture.
---

Events let modules communicate without depending on each other directly. A module dispatches an event; other modules observe it and react. Neither side knows about the other.

## Dispatching Events

Events are PHP classes that extend the `Event` base class. Dispatch them through the `EventDispatcherInterface`:

```php title="PostService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Services;

use Marko\Core\Event\EventDispatcherInterface;
use App\Blog\Events\Post\PostCreated;

readonly class PostService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createPost(
        string $title,
        string $body,
    ): Post {
        $post = new Post(title: $title, body: $body);
        // ... save to database

        $this->eventDispatcher->dispatch(new PostCreated(post: $post));

        return $post;
    }
}
```

## Defining Events

Events extend the `Marko\Core\Event\Event` base class, which provides propagation stopping:

```php title="PostCreated.php"
<?php

declare(strict_types=1);

namespace App\Blog\Events\Post;

use App\Blog\Entity\PostInterface;
use Marko\Core\Event\Event;

class PostCreated extends Event
{
    public function __construct(
        private readonly PostInterface $post,
    ) {}

    public function getPost(): PostInterface
    {
        return $this->post;
    }
}
```

## Creating Observers

An observer is a class with the `#[Observer]` attribute that handles a specific event. The `event` parameter specifies which event class to listen for:

```php title="TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace App\Analytics\Observer;

use App\Blog\Events\Post\PostCreated;
use Marko\Core\Attributes\Observer;

#[Observer(event: PostCreated::class)]
class TrackPostCreation
{
    public function handle(PostCreated $event): void
    {
        // Send to analytics, update counters, notify admins, etc.
        $postTitle = $event->getPost()->title;
        // ...
    }
}
```

Observers are discovered automatically from module `src/` directories — no manual registration needed.

## Observer Priority

When multiple observers listen to the same event, they run by priority (higher values first). Use the `priority` parameter to control order:

```php
#[Observer(event: PostCreated::class, priority: 10)]
class HighPriorityObserver
{
    public function handle(PostCreated $event): void { /* ... */ }
}

#[Observer(event: PostCreated::class, priority: 0)]
class DefaultPriorityObserver
{
    public function handle(PostCreated $event): void { /* ... */ }
}
```

## Async Observers

Add `async: true` to push the observer to the queue instead of running it inline. The event is serialized, dispatched as an `AsyncObserverJob`, and executed by a queue worker. The worker resolves the observer from the container so all normal constructor injection works.

```php
use Marko\Core\Attributes\Observer;

#[Observer(event: PostCreated::class, async: true)]
class SendPostNotification
{
    public function handle(PostCreated $event): void
    {
        // Runs in a background worker, not in the HTTP request
    }
}
```

Async observers require [`marko/queue`](/docs/packages/queue/) and a configured queue driver. The worker must be running (`marko queue:work`) for async observers to execute. Retry and failure behavior follow the same `max_attempts` and `retry_after` config as regular jobs.

### How the Queue Is Wired

Core doesn't depend on the queue. It defines `Marko\Core\Event\AsyncObserverDispatcherInterface`, and `marko/queue` binds it to `QueueAsyncObserverDispatcher`, which pushes the `AsyncObserverJob`. The dispatcher resolves that binding the first time an async observer fires, not at boot. A request that dispatches no async observers never builds the queue or opens its connection.

Async and sync observers share one priority order. An async observer is queued at its position in that order. It can't stop propagation, because it hasn't run yet when the next observer is called.

### No Queue Installed

An async observer never falls back to running inline. If one fires and nothing binds `AsyncObserverDispatcherInterface`, `dispatch()` throws an `EventException`:

```
Observer App\Blog\Observer\SendPostNotification is marked async but no queue is installed
```

The exception's suggestion lists the fixes: install `marko/queue` with a driver, or remove `async: true` to run the observer during the request. With [`marko/queue-sync`](/docs/packages/queue-sync/) as the driver, the job is pushed and run straight away, still through the queue. Exceptions from the observer come back out of `dispatch()` as a `JobFailedException`.

### Event Serializability

An async observer receives a copy of the event that was serialized when it was queued, not the original object. Keep async events to plain data: IDs, strings, numbers, arrays and other serializable value objects. Closures, resources and live connections can't be serialized. Dispatching such an event to an async observer throws a `SerializationException` that names the observer and the event class. Changes an async observer makes to its event aren't visible to the code that dispatched it.

Queued event data is signed with the same HMAC envelope as every other job, so `encryption.key` must be set when the event is dispatched, not only when the worker runs.

## Built-in Events

Marko packages dispatch events at meaningful points:

### Authentication Events

| Event | When |
|---|---|
| `LoginEvent` | User successfully logs in |
| `LogoutEvent` | User logs out |
| `FailedLoginEvent` | Login attempt fails |
| `PasswordResetEvent` | Password is reset |

### Blog Events

| Event | When |
|---|---|
| `PostCreated` | New post is created |
| `PostUpdated` | Post is modified |
| `PostDeleted` | Post is deleted |
| `CommentCreated` | New comment is added |

## When to Use Events

Events are the right tool when:

- The action and reaction belong to **different modules**
- You want **zero coupling** between the modules
- Multiple reactions might happen for the same action
- The reaction is **optional** (the system works fine without any observers)

If you need to modify a method's behavior directly, use [Plugins](/docs/concepts/plugins/) instead.

## Next Steps

- [Routing](/docs/guides/routing/) — handle HTTP requests
- [Modularity](/docs/concepts/modularity/) — how modules discover and compose
- [Core Package](/docs/packages/core/) — API reference for the event dispatcher
