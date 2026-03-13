# marko/testing

Testing utilities for Marko---reusable fakes with built-in assertions that eliminate test boilerplate.

## Installation

```bash
composer require marko/testing --dev
```

## Available Fakes

`FakeEventDispatcher`, `FakeMailer`, `FakeQueue`, `FakeSession`, `FakeCookieJar`, `FakeLogger`, `FakeConfigRepository`, `FakeAuthenticatable`, `FakeUserProvider`, `FakeGuard`

## Quick Example

```php
use Marko\Testing\Fake\FakeEventDispatcher;

$dispatcher = new FakeEventDispatcher();
$dispatcher->dispatch(new OrderPlaced($order));

$dispatcher->assertDispatched(OrderPlaced::class);
$dispatcher->assertDispatchedCount(OrderPlaced::class, 1);
$dispatcher->assertNotDispatched(OrderShipped::class);
```

## Documentation

Full usage, API reference, and examples: [marko/testing](https://marko.build/docs/packages/testing/)
