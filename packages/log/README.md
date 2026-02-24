# Marko Log

Logging contracts and formatters--define how your application logs messages without coupling to a storage backend.

## Overview

This package provides the `LoggerInterface`, log levels as a backed enum, the `LogRecord` value object, and a `LineFormatter`. It contains no storage implementation; install a driver like `marko/log-file` for actual log writing. Includes a `log:clear` CLI command for cleaning up old log files.

## Installation

```bash
composer require marko/log
```

Note: You also need an implementation package such as `marko/log-file`.

## Usage

### Logging Messages

Inject the logger interface and call level-specific methods:

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

        // On failure:
        $this->logger->error('Payment failed for order {order_id}', [
            'order_id' => $orderId,
        ]);
    }
}
```

Context placeholders (`{key}`) are interpolated automatically from the context array.

### Log Levels

Eight severity levels via the `LogLevel` enum (most to least severe):

- `Emergency` -- System unusable
- `Alert` -- Immediate action required
- `Critical` -- Critical conditions
- `Error` -- Runtime errors
- `Warning` -- Exceptional but non-error conditions
- `Notice` -- Normal but significant events
- `Info` -- Interesting events
- `Debug` -- Detailed debug information

### Using a Specific Level

```php
use Marko\Log\LogLevel;

$this->logger->log(LogLevel::Warning, 'Disk space low', [
    'free_mb' => 120,
]);
```

### CLI Command

```bash
# Clear log files older than configured max_files days
marko log:clear

# Clear log files older than 7 days
marko log:clear --days=7
```

## Customization

Replace the default log formatter via Preference:

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\Contracts\LogFormatterInterface;
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

## API Reference

### LoggerInterface

```php
interface LoggerInterface
{
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(LogLevel $level, string $message, array $context = []): void;
}
```

### LogFormatterInterface

```php
interface LogFormatterInterface
{
    public function format(LogRecord $record): string;
}
```

### LogLevel

```php
enum LogLevel: string
{
    public function severity(): int;
    public function meetsThreshold(LogLevel $minimum): bool;
    public function upperName(): string;
}
```

### LogRecord

```php
readonly class LogRecord
{
    public LogLevel $level;
    public string $message;
    public array $context;
    public DateTimeImmutable $datetime;
    public string $channel;

    public function interpolatedMessage(): string;
    public function contextAsJson(): string;
}
```
