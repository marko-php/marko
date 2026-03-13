# marko/amphp

Async event loop foundation for Marko -- runs the Revolt event loop and provides the `pubsub:listen` command for long-lived subscriber processes.

## Installation

```bash
composer require marko/amphp
```

Driver packages that require the event loop install this automatically.

## Quick Example

```php
use Marko\Amphp\EventLoopRunner;
use Revolt\EventLoop;

// Schedule async work, then run the loop
EventLoop::defer(function (): void {
    // ... async work ...
});

$eventLoopRunner->run(); // blocks until the loop exits
```

## Documentation

Full usage, API reference, and examples: [marko/amphp](https://marko.build/docs/packages/amphp/)
