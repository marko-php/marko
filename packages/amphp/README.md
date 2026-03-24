# marko/amphp

Async event loop foundation for Marko -- runs the Revolt event loop and provides the `pubsub:listen` command for long-lived subscriber processes.

## Overview

`marko/amphp` integrates the [amphp/amp](https://amphp.org/) async runtime into the Marko framework. It wraps the Revolt event loop in an `EventLoopRunner` that can be started and stopped cleanly, and registers the `pubsub:listen` console command for running long-lived subscriber processes.

Driver packages that require the async event loop install this package automatically.

## Installation

```bash
composer require marko/amphp
```

## Usage

### Running the event loop

```php
use Marko\Amphp\EventLoopRunner;
use Revolt\EventLoop;

// Schedule async work, then run the loop
EventLoop::defer(function (): void {
    // ... async work ...
});

$runner = new EventLoopRunner();
$runner->run(); // blocks until the loop exits
```

### Starting the pub/sub listener

Use the built-in console command to start a long-lived subscriber process:

```bash
php marko pubsub:listen
```

The command blocks until stopped with `Ctrl+C`, running all registered Revolt callbacks and pub/sub subscribers.

### Stopping the loop programmatically

```php
$runner->stop();       // stops the loop if running
$runner->isRunning();  // returns bool
```

## API Reference

### `EventLoopRunner`

Wraps `Revolt\EventLoop` for lifecycle management.

| Method | Return | Description |
|--------|--------|-------------|
| `run()` | `void` | Starts the event loop; blocks until the loop exits. |
| `stop()` | `void` | Stops the event loop if it is currently running. |
| `isRunning()` | `bool` | Returns whether the event loop is active. |

### `AmphpConfig`

Reads amphp-related configuration values.

| Method | Return | Description |
|--------|--------|-------------|
| `shutdownTimeout()` | `int` | Returns `amphp.shutdown_timeout` from config (milliseconds). |

### `PubSubListenCommand`

Console command (`pubsub:listen`) that starts the event loop runner and keeps the process alive for pub/sub subscribers.

## Documentation

Full usage, API reference, and examples: [marko/amphp](https://marko.build/docs/packages/amphp/)
