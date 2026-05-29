---
title: Logging
description: Log messages with pluggable backends --- file-based by default, swappable via bindings.
---

Marko's logging system separates the logging contract from its storage backend. Your application code injects `LoggerInterface` and calls level-specific methods --- the underlying driver (file, database, or any custom implementation) is configured through bindings. This guide covers setup, everyday usage, customization, and testing.

## Setup

Install the contracts package and a driver:

```bash
composer require marko/log marko/log-file
```

The `marko/log-file` package registers its binding automatically via `module.php`, wiring `LoggerInterface` to a `FileLogger` created through `FileLoggerFactory`.

### Configuration

All log settings live in `config/log.php`:

```php title="config/log.php"
<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['LOG_DRIVER'] ?? 'file',
    'path' => $_ENV['LOG_PATH'] ?? 'storage/logs',
    'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'app',
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => (int) ($_ENV['LOG_MAX_FILES'] ?? 30),
    'max_file_size' => (int) ($_ENV['LOG_MAX_FILE_SIZE'] ?? 10 * 1024 * 1024),
];
```

| Key | Default | Description |
|---|---|---|
| `driver` | `file` | Active logging driver |
| `path` | `storage/logs` | Directory where log files are written |
| `level` | `debug` | Minimum severity --- messages below this are skipped |
| `channel` | `app` | Channel name used in filenames and log output |
| `format` | `[{datetime}] {channel}.{level}: {message} {context}` | Log line format |
| `date_format` | `Y-m-d H:i:s` | Timestamp format |
| `max_files` | `30` | Days of logs to keep (used by `log:clear`) |
| `max_file_size` | `10485760` | Max file size in bytes for size-based rotation (10 MB) |

Override any value via environment variables (`LOG_DRIVER`, `LOG_PATH`, `LOG_LEVEL`, `LOG_CHANNEL`, `LOG_MAX_FILES`, `LOG_MAX_FILE_SIZE`).

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

The file driver supports two rotation strategies:

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

Replace the default `LineFormatter` with a custom formatter --- for example, JSON output:

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogRecord;

#[Preference(replaces: LineFormatter::class)]
class JsonFormatter implements LogFormatterInterface
{
    public function format(
        LogRecord $record,
    ): string {
        return json_encode([
            'level' => $record->level->value,
            'message' => $record->interpolatedMessage(),
            'channel' => $record->channel,
            'datetime' => $record->datetime->format('c'),
            'context' => $record->context,
        ]) . "\n";
    }
}
```

### Custom Log Drivers

Implement `LoggerInterface` to create an entirely new backend --- for example, a database or external service driver:

```php
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;

class DatabaseLogger implements LoggerInterface
{
    public function __construct(
        private LogLevel $minimumLevel,
    ) {}

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::Alert, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::Critical, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::Warning, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::Notice, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::Debug, $message, $context);
    }

    public function log(
        LogLevel $level,
        string $message,
        array $context = [],
    ): void {
        if (!$level->meetsThreshold($this->minimumLevel)) {
            return;
        }

        // Write to your database table here
    }
}
```

Then bind it in your `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Log\Contracts\LoggerInterface;
use App\Blog\Logger\DatabaseLogger;

return [
    'bindings' => [
        LoggerInterface::class => DatabaseLogger::class,
    ],
];
```

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
- [marko/log-file reference](/docs/packages/log-file/) --- file driver API, rotation strategies
- [Testing guide](/docs/guides/testing/) --- all available fakes and Pest expectations
