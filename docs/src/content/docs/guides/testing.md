---
title: Testing
description: Test your Marko application with Pest PHP and built-in fakes.
---

Marko uses [Pest PHP](https://pestphp.com/) for testing and provides a `marko/testing` package with fakes for all major services.

## Setup

```bash
composer require --dev marko/testing
```

## Running Tests

```bash
# Run all tests
./vendor/bin/pest --parallel

# Run with coverage
./vendor/bin/pest --parallel --coverage --min=80

# Run a specific test file
./vendor/bin/pest tests/Feature/PostTest.php
```

## Writing Tests

Pest tests are expressive and concise:

```php title="tests/Feature/PostTest.php"
<?php

declare(strict_types=1);

use App\Blog\Service\PostService;
use Marko\Testing\Fake\FakeEventDispatcher;

test('creates a post and dispatches event', function () {
    $events = new FakeEventDispatcher();
    $service = new PostService(events: $events);

    $post = $service->createPost('Hello', 'World');

    expect($post->title)->toBe('Hello')
        ->and($post->body)->toBe('World');

    $events->assertDispatched(PostCreatedEvent::class);
});
```

## Available Fakes

The `marko/testing` package provides test doubles for all major interfaces:

| Fake | Replaces |
|---|---|
| `FakeEventDispatcher` | `EventDispatcherInterface` |
| `FakeMailer` | `MailerInterface` |
| `FakeQueue` | `QueueInterface` |
| `FakeSession` | `SessionInterface` |
| `FakeCookieJar` | `CookieJarInterface` |
| `FakeLogger` | `LoggerInterface` |
| `FakeConfigRepository` | `ConfigRepositoryInterface` |
| `FakeAuthenticatable` | `AuthenticatableInterface` |
| `FakeUserProvider` | `UserProviderInterface` |

Each fake has `assertXxx()` methods for verifying behavior:

```php
$mailer = new FakeMailer();

// ... code that sends mail

$mailer->assertSent();
$mailer->assertSentCount(1);
```

## Custom Pest Expectations

Marko auto-loads custom expectations for cleaner assertions:

```php
// Instead of: $events->assertDispatched(PostCreatedEvent::class)
expect($events)->toHaveDispatched(PostCreatedEvent::class);

// Instead of: $mailer->assertSent()
expect($mailer)->toHaveSent();

// Instead of: $queue->assertPushed(SendEmail::class)
expect($queue)->toHavePushed(SendEmail::class);

// Instead of: $logger->assertLogged(...)
expect($logger)->toHaveLogged('User logged in');
```

## Testing with FakeConfigRepository

Pass a flat dot-notation array:

```php
$config = new FakeConfigRepository([
    'auth.defaults.guard' => 'web',
    'auth.guards.web.driver' => 'session',
]);

$service = new AuthService(config: $config);
```

## Test Naming Conventions

Marko tests use present tense, concise names:

```php
// Good
test('creates a post with valid data', function () { /* ... */ });
test('throws when title is empty', function () { /* ... */ });

// Avoid
test('it should be able to demonstrate creating a post', function () { /* ... */ });
```

## Next Steps

- [Error Handling](/docs/guides/error-handling/) — test error scenarios
- [Authentication](/docs/guides/authentication/) — test auth flows with fakes
- [Testing package reference](/docs/packages/testing/) — full API details
