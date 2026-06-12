---
title: marko/amphp
description: Async event loop foundation for Marko — runs the Revolt event loop and provides the pubsub:listen command for long-lived subscriber processes.
---

Async event loop foundation for Marko — runs the [Revolt event loop](https://revolt.run) and provides the `pubsub:listen` command for long-lived subscriber processes. This package provides `EventLoopRunner`, which wraps `EventLoop::run()` with lifecycle control, and `PubSubListenCommand`, which subscribes to the configured channels via [`marko/pubsub`](/docs/packages/pubsub/) and dispatches each message through `MessageHandlerInterface`. Driver packages ([marko/pubsub-redis](/docs/packages/pubsub-redis/), [marko/pubsub-pgsql](/docs/packages/pubsub-pgsql/)) depend on this package for their async I/O. You only need to interact with it directly if you are writing an async driver or need to manage the event loop lifecycle yourself.

## Installation

```bash
composer require marko/amphp
```

`marko/amphp` requires `marko/pubsub`. Driver packages that require the event loop install this automatically.

## Configuration

```php title="config/amphp.php"
return [
    'shutdown_timeout' => (int) ($_ENV['AMPHP_SHUTDOWN_TIMEOUT'] ?? 30),
    'channels'         => array_filter(explode(',', (string) ($_ENV['AMPHP_CHANNELS'] ?? ''))),
];
```

| Key                | Env var                    | Default | Description |
|--------------------|----------------------------|---------|-------------|
| `shutdown_timeout` | `AMPHP_SHUTDOWN_TIMEOUT`   | `30`    | Seconds to wait for graceful shutdown on SIGINT |
| `channels`         | `AMPHP_CHANNELS`           | `[]`    | Comma-separated list of pub/sub channels the `pubsub:listen` command subscribes to |

The `channels` key is required for `pubsub:listen` to function. If it is empty, the command throws `AmphpException::noChannelsConfigured()` on startup.

## Usage

### Starting the subscriber process

Configure which channels to subscribe to via the `AMPHP_CHANNELS` environment variable (comma-separated), then run `pubsub:listen`:

```bash
AMPHP_CHANNELS=orders,notifications marko pubsub:listen
```

Or set `channels` in `config/amphp.php`:

```php title="config/amphp.php"
return [
    'shutdown_timeout' => 30,
    'channels'         => ['orders', 'notifications'],
];
```

Then start the listener:

```bash
marko pubsub:listen
```

The command subscribes to all configured channels via `SubscriberInterface`, dispatches each incoming message through `MessageHandlerInterface`, and handles `SIGINT` for graceful shutdown. Press `Ctrl+C` to stop the listener gracefully.

### Development server auto-detection

When using [marko/devserver](/docs/packages/devserver/), add `pubsub:listen` to your processes configuration so it starts alongside the web server:

```php title="config/dev.php"
return [
    'processes' => [
        'pubsub' => 'marko pubsub:listen',
    ],
];
```

### When you need this package directly

Most applications do not interact with `EventLoopRunner` or `AmphpConfig` directly. You need this package when:

- Writing a custom async driver that schedules work on the Revolt event loop
- Testing code that needs to control event loop start/stop
- Configuring the shutdown timeout via `AMPHP_SHUTDOWN_TIMEOUT` environment variable

```php
use Marko\Amphp\EventLoopRunner;
use Revolt\EventLoop;

class MyAsyncDriver
{
    public function __construct(
        private EventLoopRunner $eventLoopRunner,
    ) {}

    public function start(): void
    {
        // Schedule async work on the event loop before running
        EventLoop::defer(function (): void {
            // ... async work ...
        });

        $this->eventLoopRunner->run(); // blocks until the loop exits
    }

    public function stop(): void
    {
        $this->eventLoopRunner->stop();
    }
}
```

## API Reference

### EventLoopRunner

```php
public function run(): void;
public function stop(): void;
public function isRunning(): bool;
```

### AmphpConfig

```php
use Marko\Config\ConfigRepositoryInterface;

public function __construct(private ConfigRepositoryInterface $config)
public function shutdownTimeout(): int;
public function channels(): array; // Returns list<string> of channel names
```

### PubSubListenCommand

Registered as `pubsub:listen`. No public API beyond the `CommandInterface` contract.

### AmphpException

Extends `MarkoException` — the base exception for all errors thrown by this package.
