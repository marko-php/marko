# marko/testing

Testing utilities for Marko---reusable fakes with built-in assertions that eliminate test boilerplate.

## Overview

`marko/testing` provides a set of in-memory fakes that replace real infrastructure dependencies during tests. Instead of mocking interfaces by hand or spinning up real services, you drop in a fake, run your code, and call assertion methods directly on the fake. This removes hundreds of lines of boilerplate and keeps tests focused on behavior.

## Installation

```bash
composer require marko/testing --dev
```

## Available Fakes

`FakeEventDispatcher`, `FakeMailer`, `FakeQueue`, `FakeSession`, `FakeCookieJar`, `FakeLogger`, `FakeConfigRepository`, `FakeAuthenticatable`, `FakeUserProvider`, `FakeGuard`

## Usage

### FakeEventDispatcher

```php
use Marko\Testing\Fake\FakeEventDispatcher;

$dispatcher = new FakeEventDispatcher();
$dispatcher->dispatch(new OrderPlaced($order));

$dispatcher->assertDispatched(OrderPlaced::class);
$dispatcher->assertDispatchedCount(OrderPlaced::class, 1);
$dispatcher->assertNotDispatched(OrderShipped::class);
```

### FakeMailer

```php
use Marko\Testing\Fake\FakeMailer;

$mailer = new FakeMailer();
$mailer->send($message);

$mailer->assertSent(WelcomeEmail::class);
$mailer->assertSentCount(WelcomeEmail::class, 1);
$mailer->assertNothingSent();
```

### FakeQueue

```php
use Marko\Testing\Fake\FakeQueue;

$queue = new FakeQueue();
$queue->push(new ProcessOrder($order));

$queue->assertPushed(ProcessOrder::class);
$queue->assertPushedCount(ProcessOrder::class, 1);
$queue->assertNotPushed(SendInvoice::class);
$queue->assertNothingPushed();
```

### FakeSession

```php
use Marko\Testing\Fake\FakeSession;

$session = new FakeSession();
$session->put('user_id', 42);

$value = $session->get('user_id'); // 42
$session->forget('user_id');
```

### FakeCookieJar

```php
use Marko\Testing\Fake\FakeCookieJar;

$cookies = new FakeCookieJar();
$cookies->set('token', 'abc123');

$value = $cookies->get('token'); // 'abc123'
```

### FakeLogger

```php
use Marko\Testing\Fake\FakeLogger;

$logger = new FakeLogger();
$logger->info('User logged in');
$logger->error('Something failed');

$logger->assertLogged('User logged in');
$logger->assertNothingLogged();
```

### FakeConfigRepository

```php
use Marko\Testing\Fake\FakeConfigRepository;

$config = new FakeConfigRepository([
    'auth.defaults.guard' => 'web',
    'app.name' => 'Marko',
]);

$value = $config->get('auth.defaults.guard'); // 'web'
```

### FakeAuthenticatable

```php
use Marko\Testing\Fake\FakeAuthenticatable;

$user = new FakeAuthenticatable(id: 1, password: 'hashed-secret');

$user->getAuthIdentifier(); // 1
$user->getAuthPassword();   // 'hashed-secret'
```

### FakeUserProvider

```php
use Marko\Testing\Fake\FakeUserProvider;
use Marko\Testing\Fake\FakeAuthenticatable;

$user = new FakeAuthenticatable(id: 1);
$provider = new FakeUserProvider($user);

$found = $provider->retrieveById(1); // returns $user
```

### FakeGuard

```php
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeAuthenticatable;

$guard = new FakeGuard(name: 'web', attemptResult: true);

// Set a user directly
$user = new FakeAuthenticatable(id: 1);
$guard->setUser($user);
$guard->assertAuthenticated();

// Test login attempt
$guard->attempt(['email' => 'user@example.com', 'password' => 'secret']);
$guard->assertAttempted();

// Assert no user logged in
$guard->logout();
$guard->assertGuest();
$guard->assertLoggedOut();
```

## API Reference

### FakeEventDispatcher

- `dispatch($event): void` — Record a dispatched event
- `dispatched(string $class): array` — Return all dispatched events of a class
- `assertDispatched(string $class): void` — Assert an event was dispatched
- `assertNotDispatched(string $class): void` — Assert an event was not dispatched
- `assertDispatchedCount(string $class, int $count): void` — Assert exact dispatch count

### FakeMailer

- `send($message): void` — Record a sent message
- `assertSent(string $class): void` — Assert a message was sent
- `assertNothingSent(): void` — Assert no messages were sent
- `assertSentCount(string $class, int $count): void` — Assert exact sent count

### FakeQueue

- `push($job): void` — Record a pushed job
- `assertPushed(string $class): void` — Assert a job was pushed
- `assertNotPushed(string $class): void` — Assert a job was not pushed
- `assertPushedCount(string $class, int $count): void` — Assert exact pushed count
- `assertNothingPushed(): void` — Assert no jobs were pushed

### FakeLogger

- `info(string $message): void`, `error()`, `warning()`, etc. — Record log entries
- `assertLogged(string $message): void` — Assert a message was logged
- `assertNothingLogged(): void` — Assert nothing was logged

### FakeGuard

- `new FakeGuard(string $name, bool $attemptResult)` — Create guard; `$attemptResult` controls what `attempt()` returns
- `setUser(?AuthenticatableInterface $user): void` — Set the current authenticated user
- `attempt(array $credentials): bool` — Record credentials attempt and return configured result
- `login(AuthenticatableInterface $user): void` — Log in a user directly
- `logout(): void` — Clear current user and record logout
- `assertAuthenticated(): void` — Assert a user is currently authenticated
- `assertGuest(): void` — Assert no user is authenticated
- `assertAttempted(?callable $callback = null): void` — Assert attempt() was called, optionally matching credentials via callback
- `assertNotAttempted(): void` — Assert attempt() was never called
- `assertLoggedOut(): void` — Assert logout() was called

## Pest Expectations

`marko/testing` ships Pest custom expectations that are auto-loaded via `autoload.files`.

```php
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeQueue;
use Marko\Testing\Fake\FakeLogger;

// FakeEventDispatcher
expect($dispatcher)->toHaveDispatched(OrderPlaced::class);

// FakeMailer
expect($mailer)->toHaveSent();
expect($mailer)->toHaveSent(fn ($msg) => $msg instanceof WelcomeEmail);

// FakeQueue
expect($queue)->toHavePushed(ProcessOrder::class);

// FakeLogger
expect($logger)->toHaveLogged('User logged in');

// FakeGuard
expect($guard)->toHaveAttempted();
expect($guard)->toHaveAttempted(fn ($creds) => $creds['email'] === 'user@example.com');
expect($guard)->toBeAuthenticated();
```

## Documentation

Full usage, API reference, and examples: [marko/testing](https://marko.build/docs/packages/testing/)
