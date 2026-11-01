---
title: Logging
description: Log messages with pluggable handlers --- files by default, stderr for containers, stackable by configuration.
---

Marko's logging system separates the logging contract from where records are written. Your application code injects `LoggerInterface` and calls level-specific methods. The shared `Logger` from `marko/log` applies the level threshold and passes each record to the handlers named in `LOG_HANDLERS` --- files, stderr, or any custom handler. This guide covers setup, everyday usage, containers, customization, and testing.

## Setup

Apps created from [`marko/skeleton`](/docs/packages/skeleton/) already have `marko/log`, `marko/log-file` and `marko/log-stream`, with `LOG_HANDLERS=file` in `.env.example`. Otherwise, install the contracts package and a handler package:

```bash
composer require marko/log marko/log-file
```

`marko/log` binds `LoggerInterface` to its `Logger`. `marko/log-file` adds the `file` handler, which is the default in `LOG_HANDLERS`, so nothing else needs wiring. For containers, add (or use instead) [`marko/log-stream`](/docs/packages/log-stream/) --- see [Logging in Containers](#logging-in-containers).

### Configuration

All log settings live in `config/log.php`:

```php title="config/log.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'handlers' => Env::list('LOG_HANDLERS', ['file']),
    'formatter' => Env::string('LOG_FORMATTER', 'line'),
    'path' => Env::string('LOG_PATH', 'storage/logs'),
    'level' => Env::string('LOG_LEVEL', 'debug'),
    'channel' => Env::string('LOG_CHANNEL', 'app'),
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => Env::int('LOG_MAX_FILES', 30, min: 0),
    'max_file_size' => Env::int('LOG_MAX_FILE_SIZE', 10 * 1024 * 1024, min: 0),
    'escape_newlines' => true,
    'redact_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
    ],
    'file_mode' => 0600,
    'dir_mode' => 0700,
];
```

| Key | Default | Description |
|---|---|---|
| `handlers` | `file` | Handlers that receive every record, in order (`file`, `stream`, or several: `file,stream`) |
| `formatter` | `line` | `line` for the `format` below, or `json` for one JSON object per line |
| `path` | `storage/logs` | Directory where log files are written |
| `level` | `debug` | Minimum severity --- messages below this are skipped |
| `channel` | `app` | Channel name used in filenames and log output |
| `format` | `[{datetime}] {channel}.{level}: {message} {context}` | Log line format |
| `date_format` | `Y-m-d H:i:s` | Timestamp format |
| `max_files` | `30` | Days of logs to keep (used by `log:clear`) |
| `max_file_size` | `10485760` | Max file size in bytes for size-based rotation (10 MB) |
| `escape_newlines` | `true` | Escape CR/LF in messages so each record stays on one line (other control characters are always escaped) |
| `redact_keys` | `password`, `password_confirmation`, `token`, `secret`, `api_key`, `authorization` | Context keys (case-insensitive, at any depth) whose values are written as `[redacted]` |
| `file_mode` | `0600` | Permissions applied to newly created log files |
| `dir_mode` | `0700` | Permissions applied to a newly created log directory |

Override any value via environment variables (`LOG_HANDLERS`, `LOG_FORMATTER`, `LOG_PATH`, `LOG_LEVEL`, `LOG_CHANNEL`, `LOG_MAX_FILES`, `LOG_MAX_FILE_SIZE`). An unknown or uninstalled name in `LOG_HANDLERS` throws `InvalidLogConfigException`, listing the installed handlers.

## Core Usage

### Logging Messages

Inject `LoggerInterface` and call level-specific methods:

```php
use Marko\Log\Contracts\LoggerInterface;

class OrderService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function placeOrder(
        int $orderId,
    ): void {
        $this->logger->info('Order placed', ['order_id' => $orderId]);
    }
}
```

### Log Levels

Eight severity levels via the `LogLevel` enum, from most to least severe:

| Level | Description |
|---|---|
| `Emergency` | System unusable |
| `Alert` | Immediate action required |
| `Critical` | Critical conditions |
| `Error` | Runtime errors |
| `Warning` | Exceptional but non-error conditions |
| `Notice` | Normal but significant events |
| `Info` | Interesting events |
| `Debug` | Detailed debug information |

Use level-specific methods for convenience, or pass a `LogLevel` explicitly:

```php
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;

$this->logger->error('Payment gateway timeout');
$this->logger->warning('Disk space low', ['free_mb' => 120]);
$this->logger->debug('Cache miss', ['key' => 'user.42']);

// Equivalent to $this->logger->warning(...)
$this->logger->log(LogLevel::Warning, 'Disk space low', ['free_mb' => 120]);
```

Messages below the configured minimum level are silently skipped. For example, if `log.level` is set to `warning`, calls to `info()` and `debug()` produce no output.

### Contextual Data

Every log method accepts a context array. Context values are attached to the log record and serialized as JSON in the output:

```php
use Marko\Log\Contracts\LoggerInterface;

$this->logger->error('Import failed', [
    'file' => 'products.csv',
    'line' => 42,
    'reason' => 'Invalid SKU format',
]);
```

Output:

```
[2025-01-15 10:30:00] app.ERROR: Import failed {"file":"products.csv","line":42,"reason":"Invalid SKU format"}
```

### Message Placeholders

Context values can be interpolated into the message using PSR-3 style `{key}` placeholders:

```php
use Marko\Log\Contracts\LoggerInterface;

$this->logger->error('Payment failed for order {order_id}', [
    'order_id' => 1234,
]);
```

The `{order_id}` placeholder is replaced with `1234` in the formatted output. Placeholders work with string, numeric, and `__toString`-capable values.

### File Rotation

The `file` handler supports two rotation strategies:

**Daily rotation** (default) --- one file per day, date embedded in the filename:

```
storage/logs/app-2025-01-15.log
storage/logs/app-2025-01-16.log
```

**Size rotation** --- rotates when a file exceeds the configured size limit (default: 10 MB):

```
storage/logs/app.log
storage/logs/app.1.log
storage/logs/app.2.log
```

### Clearing Old Logs

Use the CLI command to remove old log files:

```bash
# Clear logs older than configured max_files days (default: 30)
marko log:clear

# Clear logs older than 7 days
marko log:clear --days=7
```

## Logging in Containers

In Docker, Kubernetes, Fly, Cloud Run, ECS and similar platforms, the platform collects a process's stderr and stdout, then ships and rotates it. Log files written inside a container fill its ephemeral disk, and nothing collects them. Install the stream handler:

```bash
composer require marko/log-stream
```

Then choose handlers per environment --- the two packages install side by side:

```bash
# .env in development: files, plus the terminal
LOG_HANDLERS=file,stream

# Container environment in production: stderr only, one JSON object per line
LOG_HANDLERS=stream
LOG_FORMATTER=json
```

Each listed handler receives every record, in order. JSON output keeps exceptions, nested context and multi-line messages on one line, which log collectors such as CloudWatch, Loki and Datadog parse without extra configuration. Under PHP-FPM, set `catch_workers_output = yes` in the pool so worker stderr reaches the container output. Under RoadRunner keep the default `php://stderr` target: stdout carries RoadRunner's worker protocol, and a `php://stdout` target throws. See [`marko/log-stream`](/docs/packages/log-stream/) for details.

## Logging in Production

Pick handlers by where the app runs, not by what is installed:

- **Containers and PaaS** (Docker, Kubernetes, Fly, Cloud Run, Heroku and similar) --- `LOG_HANDLERS=stream`. The platform collects stdout/stderr, so add `LOG_FORMATTER=json` for its log collector.
- **Servers you SSH into** --- `LOG_HANDLERS=file`. `marko/log-file` rotates the files (see [File Rotation](#file-rotation)); schedule `marko log:clear` to prune old ones.
- **Both** --- `LOG_HANDLERS=file,stream` writes every record to each.
- **RoadRunner** --- `marko/log-stream` must target stderr (the default `LOG_STREAM_TARGET=php://stderr`). Stdout carries RoadRunner's worker protocol.

Config is compiled into the [discovery cache](/docs/packages/core/#deploying-to-production), so run `marko discovery:cache` again after changing `LOG_HANDLERS`.

## Customization

### Swapping Rotation Strategies

Replace the default daily rotation with size-based rotation via [Preference](/docs/packages/core/):

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\SizeRotation;

#[Preference(replaces: DailyRotation::class)]
class LargeFileRotation extends SizeRotation
{
    public function __construct()
    {
        parent::__construct(maxSize: 50 * 1024 * 1024); // 50 MB
    }
}
```

### Custom Log Formatters

For JSON output, set `LOG_FORMATTER=json` --- the built-in `JsonFormatter` redacts the same keys as `LineFormatter` and safely encodes objects, exceptions and invalid UTF-8. For any other format, replace a built-in formatter via [Preference](/docs/packages/core/):

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogRecord;

#[Preference(replaces: LineFormatter::class)]
class CompactFormatter implements LogFormatterInterface
{
    public function format(
        LogRecord $record,
    ): string {
        return $record->level->upperName() . ' ' . $record->interpolatedMessage() . "\n";
    }
}
```

### Custom Log Handlers

Implement `LogHandlerInterface` to write records somewhere new --- for example, a database table. The `Logger` has already applied the level threshold and built the record, so a handler only writes it:

```php
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\LogRecord;

class DatabaseLogHandler implements LogHandlerInterface
{
    public function handle(
        LogRecord $record,
    ): void {
        // Insert $record->level, $record->message, $record->context ... into your table.
        // Throw LogWriteException when the write fails.
    }
}
```

Give it a name in `log.handler_map` from your module's `config/log.php`, which is merged with the framework's:

```php title="config/log.php"
<?php

declare(strict_types=1);

use App\Blog\Logger\DatabaseLogHandler;

return [
    'handler_map' => [
        'database' => DatabaseLogHandler::class,
    ],
];
```

Then add it to the stack with `LOG_HANDLERS=file,database`. Handlers are resolved from the container, so constructor dependencies are autowired.

## Testing

The `marko/testing` package provides `FakeLogger` --- an in-memory implementation of `LoggerInterface` that captures all log entries for assertions.

```php
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeLogger;

$logger = new FakeLogger();

$logger->info('Order placed', ['order_id' => 42]);
$logger->error('Payment failed');

// Assert a message was logged
$logger->assertLogged('Order placed');

// Assert a message was logged at a specific level
$logger->assertLogged('Payment failed', LogLevel::Error);

// Assert nothing was logged
$logger = new FakeLogger();
$logger->assertNothingLogged();
```

### Filtering by Level

Use `entriesForLevel()` to inspect entries at a specific severity:

```php
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeLogger;

$logger = new FakeLogger();
$logger->info('Info message');
$logger->error('Error message');

$errors = $logger->entriesForLevel(LogLevel::Error);
// [['level' => LogLevel::Error, 'message' => 'Error message', 'context' => []]]
```

### Pest Expectations

Marko auto-loads a `toHaveLogged` expectation for cleaner test assertions:

```php
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeLogger;

$logger = new FakeLogger();
$logger->warning('Slow query detected');

expect($logger)->toHaveLogged('Slow query detected');
expect($logger)->toHaveLogged('Slow query detected', LogLevel::Warning);
```

### Testing a Service

Pass `FakeLogger` as a constructor dependency to verify logging behavior:

```php
use Marko\Testing\Fake\FakeLogger;

it('logs when an order is placed', function () {
    $logger = new FakeLogger();
    $service = new OrderService(logger: $logger);

    $service->placeOrder(orderId: 42);

    expect($logger)->toHaveLogged('Order placed');
});
```

## Next Steps

- [marko/log reference](/docs/packages/log/) --- full API, exceptions, and `LogRecord` details
- [marko/log-file reference](/docs/packages/log-file/) --- file handler API, rotation strategies
- [marko/log-stream reference](/docs/packages/log-stream/) --- stderr/stdout handler for containers
- [Testing guide](/docs/guides/testing/) --- all available fakes and Pest expectations
