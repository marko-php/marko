---
title: Events & Observers
description: Decouple your code with event-driven architecture.
---

Events let modules communicate without depending on each other directly. A module dispatches an event; other modules observe it and react. Neither side knows about the other.

## Dispatching Events

Events are plain PHP classes. Dispatch them through the `EventDispatcherInterface`:

```php title="PostService.php"
<?php

declare(strict_types=1);

namespace Marko\Blog\Service;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Blog\Event\PostCreatedEvent;

class PostService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createPost(string $title, string $body): Post
    {
        $post = new Post(title: $title, body: $body);
        // ... save to database

        $this->eventDispatcher->dispatch(new PostCreatedEvent(post: $post));

        return $post;
    }
}
```

## Defining Events

Events are simple value objects — no base class needed:

```php title="PostCreatedEvent.php"
<?php

declare(strict_types=1);

namespace Marko\Blog\Event;

class PostCreatedEvent
{
    public function __construct(
        public readonly Post $post,
    ) {}
}
```

## Creating Observers

An observer is a class that handles a specific event:

```php title="TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace App\Analytics\Observer;

use Marko\Blog\Event\PostCreatedEvent;

class TrackPostCreation
{
    public function handle(PostCreatedEvent $event): void
    {
        // Send to analytics, update counters, notify admins, etc.
        $postTitle = $event->post->title;
        // ...
    }
}
```

## Registering Observers

Observers are registered in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use App\Analytics\Observer\TrackPostCreation;
use Marko\Blog\Event\PostCreatedEvent;

return [
    'observers' => [
        PostCreatedEvent::class => [
            TrackPostCreation::class,
        ],
    ],
];
```

## Observer Priority

When multiple observers listen to the same event, they run in module priority order (`app/` → `modules/` → `vendor/`). Within the same module, observers run in the order they're listed.

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
| `PostCreatedEvent` | New post is created |
| `PostUpdatedEvent` | Post is modified |
| `PostDeletedEvent` | Post is deleted |
| `CommentCreatedEvent` | New comment is added |

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
