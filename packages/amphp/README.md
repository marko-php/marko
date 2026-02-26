# Marko AMPHP

Async event loop foundation for Marko -- runs the Revolt event loop and provides the `pubsub:listen` command for long-lived subscriber processes.

## Overview

`marko/amphp` integrates the [Revolt event loop](https://revolt.run) into Marko. It provides `EventLoopRunner`, which wraps `EventLoop::run()` with lifecycle control, and `PubSubListenCommand`, which starts the subscriber process from the CLI. Driver packages (`marko/pubsub-redis`, `marko/pubsub-pgsql`) depend on this package for their async I/O. You only need to interact with it directly if you are writing an async driver or need to manage the event loop lifecycle yourself.

## Installation

```bash
composer require marko/amphp
```

Driver packages that require the event loop install this automatically.

## Usage

### Starting the subscriber process

Run the `pubsub:listen` command to start a long-lived process that drives the async event loop and processes incoming pub/sub messages:

```bash
php marko pubsub:listen
```

Press `Ctrl+C` to stop the listener gracefully.

### Development server auto-detection

When using `marko/dev-server`, add `pubsub:listen` to your processes configuration so it starts alongside the web server:

```php
// config/dev-server.php
return [
    'processes' => [
        'pubsub' => 'php marko pubsub:listen',
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

class MyAsyncDriver
{
    public function __construct(
        private EventLoopRunner $runner,
    ) {}

    public function start(): void
    {
        // Schedule async work on the event loop before running
        \Revolt\EventLoop::defer(function (): void {
            // ... async work ...
        });

        $this->runner->run(); // blocks until the loop exits
    }

    public function stop(): void
    {
        $this->runner->stop();
    }
}
```

### Configuration

```php
// config/amphp.php
return [
    'shutdown_timeout' => (int) ($_ENV['AMPHP_SHUTDOWN_TIMEOUT'] ?? 30),
];
```

| Key                | Env var                    | Default |
|--------------------|----------------------------|---------|
| `shutdown_timeout` | `AMPHP_SHUTDOWN_TIMEOUT`   | `30`    |

## API Reference

### EventLoopRunner

```php
public function __construct(private AmphpConfig $config)
public function run(): void;
public function stop(): void;
public function isRunning(): bool;
```

### AmphpConfig

```php
public function __construct(private ConfigRepositoryInterface $config)
public function shutdownTimeout(): int;
```

### PubSubListenCommand

Registered as `pubsub:listen`. No public API beyond the `CommandInterface` contract.
