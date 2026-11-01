---
title: marko/log
description: Logging contracts, the shared logger and formatters -- define how your application logs messages without coupling to a storage backend.
---

Logging contracts, the shared logger and formatters --- define how your application logs messages without coupling to a storage backend. This package provides `LoggerInterface` and the `Logger` it is bound to, the `LogHandlerInterface` that handler packages implement, log levels as a backed enum, the `LogRecord` value object, `LineFormatter` and `JsonFormatter`, and typed `LogConfig` for accessing log settings. The `Logger` applies the level threshold, builds each record and passes it to every configured handler. It writes nothing itself: install a handler package such as [`marko/log-file`](/docs/packages/log-file/) or [`marko/log-stream`](/docs/packages/log-stream/).

## Installation

```bash
composer require marko/log
```

Note: You also need a handler package such as `marko/log-file` or `marko/log-stream`. Without one, resolving `LoggerInterface` throws `NoDriverException`, which lists the packages to install.

## Configuration

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

| Key | Env | Default | Description |
|---|---|---|---|
| `handlers` | `LOG_HANDLERS` | `file` | Handlers that receive every record, in order. A comma-separated list stacks them: `file,stream` |
| `formatter` | `LOG_FORMATTER` | `line` | `line` (the `format` below) or `json` (one JSON object per line) |
| `level` | `LOG_LEVEL` | `debug` | Minimum severity --- messages below this are skipped |
| `channel` | `LOG_CHANNEL` | `app` | Channel name in each record (and in log file names) |
| `format`, `date_format`, `escape_newlines` | --- | see above | `LineFormatter` settings |
| `redact_keys` | --- | see above | Context keys masked by both formatters |
| `path`, `max_files`, `max_file_size`, `file_mode`, `dir_mode` | `LOG_PATH`, ... | see above | File settings, read by [`marko/log-file`](/docs/packages/log-file/) and `log:clear` |

### Handlers

Each handler package adds its name to `log.handler_map` through its own `config/log.php`, merged with this one. Installing a package makes its name available; `log.handlers` picks which run:

| Name | Package | Writes to |
|---|---|---|
| `file` | [`marko/log-file`](/docs/packages/log-file/) | Rotated files in `log.path` |
| `stream` | [`marko/log-stream`](/docs/packages/log-stream/) | `php://stderr` or `php://stdout` |

Handler packages don't bind `LoggerInterface`, so several can be installed together and switched per environment:

```bash
# Development: files, plus the terminal
LOG_HANDLERS=file,stream

# Production container: stderr only, as JSON for the log collector
LOG_HANDLERS=stream
LOG_FORMATTER=json
```

An unknown or uninstalled name in `log.handlers` throws `InvalidLogConfigException`, listing the installed handlers. So does an empty list, or a name listed twice. Nothing is silently dropped.

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

- `Emergency` --- System unusable
- `Alert` --- Immediate action required
- `Critical` --- Critical conditions
- `Error` --- Runtime errors
- `Warning` --- Exceptional but non-error conditions
- `Notice` --- Normal but significant events
- `Info` --- Interesting events
- `Debug` --- Detailed debug information

Each level has a numeric severity (lower numbers are more severe). Use `meetsThreshold()` to check whether a level is severe enough:

```php
use Marko\Log\LogLevel;

LogLevel::Error->meetsThreshold(LogLevel::Warning); // true (Error is more severe)
LogLevel::Debug->meetsThreshold(LogLevel::Warning); // false
```

### Using a Specific Level

```php
use Marko\Log\LogLevel;

$this->logger->log(LogLevel::Warning, 'Disk space low', [
    'free_mb' => 120,
]);
```

### Reading Configuration

The `LogConfig` class provides typed access to log configuration values:

```php
use Marko\Log\Config\LogConfig;

class MyService
{
    public function __construct(
        private LogConfig $logConfig,
    ) {}

    public function setup(): void
    {
        $handlers = $this->logConfig->handlers(); // ['file' => FileLogHandler::class, ...]
        $formatter = $this->logConfig->formatter(); // 'line' or 'json'
        $path = $this->logConfig->path();
        $level = $this->logConfig->level();
        $channel = $this->logConfig->channel();
        $format = $this->logConfig->format();
        $dateFormat = $this->logConfig->dateFormat();
        $maxFiles = $this->logConfig->maxFiles();
        $maxFileSize = $this->logConfig->maxFileSize();
        $escapeNewlines = $this->logConfig->escapeNewlines(); // default true
        $redactKeys = $this->logConfig->redactKeys(); // password, token, secret, ...
        $fileMode = $this->logConfig->fileMode(); // default 0600
        $dirMode = $this->logConfig->dirMode(); // default 0700
    }
}
```

### CLI Commands

| Command | Description |
|---------|-------------|
| `marko log:clear` | Clear log files older than configured `max_files` days |
| `marko log:clear --days=7` | Clear log files older than 7 days |

`log:clear` measures file age against the PSR-20 `ClockInterface` bound by [`marko/clock`](/docs/packages/clock/), so the cutoff is "now" on the same clock the rest of the application uses (a [`FakeClock`](/docs/packages/testing/#fakeclock) in tests).

## Customization

### JSON Output

Set `LOG_FORMATTER=json` to write each record as one JSON object per line, for log collectors:

```
{"datetime":"2026-01-21T10:30:45.000+00:00","level":"error","channel":"app","message":"Payment failed for order 42","context":{"order_id":42}}
```

`JsonFormatter` applies the same `redact_keys` as `LineFormatter`, then makes every context value encodable instead of failing: an object becomes its class name, or its `__toString()` value when it defines one. An exception becomes `class`, `message`, `code`, `file`, `line`, `trace` and `previous`. Arrays deeper than 10 levels (including recursive ones) are cut off with `[max depth reached]`, invalid UTF-8 is replaced with U+FFFD, `INF`/`NAN` become strings and resources become their type. JSON escapes newlines, control characters and U+2028/U+2029, so a record is always one line.

### Custom Formatters

Replace a built-in formatter via [Preference](/docs/packages/core/):

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

### Custom Handlers

To log somewhere new (a database, a queue, an external service), implement `LogHandlerInterface` and add it to `log.handler_map`. The `Logger` has already applied the level threshold, so a handler writes every record it receives, and throws `LogWriteException` when it can't:

```php
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\LogRecord;

class DatabaseLogHandler implements LogHandlerInterface
{
    public function __construct(
        private LogFormatterInterface $logFormatter,
    ) {}

    public function handle(
        LogRecord $record,
    ): void {
        // Insert $record (or $this->logFormatter->format($record)) into your table
    }
}
```

```php title="config/log.php"
return [
    'handler_map' => [
        'database' => DatabaseLogHandler::class,
    ],
];
```

Then set `LOG_HANDLERS=file,database`. Handlers are resolved from the container, so constructor dependencies are autowired; add a `module.php` binding when construction needs config values.

### Line Format

The default `LineFormatter` uses the format `[{datetime}] {channel}.{level}: {message} {context}` and accepts custom format and date format strings via its constructor. The formatter neutralizes untrusted input before it reaches the log file:

- **Control characters** --- in the interpolated message, backslashes are escaped first (`\` becomes `\\`, so a literal `\n` in the input can never be confused with an escaped newline), then every C0 control, DEL, C1 control and the U+2028/U+2029 line terminators are written as escape sequences (`\t`, `\x1b`, `\u0085`, ` `, ...). An ANSI sequence in a value such as `"Login failed for {username}"` therefore cannot repaint an admin's terminal during `tail -f`. In the context JSON the same characters become `\uXXXX` escapes, so it stays valid JSON.
- **Newlines** --- the `escapeNewlines` boolean (default `true`, `log.escape_newlines`) controls whether CR and LF in the message are written as `\r` and `\n`, which prevents log line-injection where one message masquerades as several entries. Disable it only if a downstream log processor requires literal newlines; the other control characters are escaped regardless.
- **Redaction** --- the `redactKeys` list (`log.redact_keys`, default `password`, `password_confirmation`, `token`, `secret`, `api_key`, `authorization`) replaces the value of any matching context key with `[redacted]`. Keys match case-insensitively at any nesting depth of context arrays, and redaction happens before interpolation, so `{token}` in a message is masked too. Pass an empty list to disable it. Objects in the context are not traversed --- convert them to arrays first if they carry secrets.

## API Reference

### LoggerInterface

```php
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;

public function emergency(string $message, array $context = []): void;
public function alert(string $message, array $context = []): void;
public function critical(string $message, array $context = []): void;
public function error(string $message, array $context = []): void;
public function warning(string $message, array $context = []): void;
public function notice(string $message, array $context = []): void;
public function info(string $message, array $context = []): void;
public function debug(string $message, array $context = []): void;
public function log(LogLevel $level, string $message, array $context = []): void;
```

### Logger

The `LoggerInterface` implementation that `module.php` binds, built by `LoggerFactory` from `log.handlers`:

```php
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Psr\Clock\ClockInterface;

public function __construct(
    array $handlers, // list<LogHandlerInterface>, at least one
    string $channel,
    LogLevel $minimumLevel,
    ClockInterface $clock,
);
```

### LogHandlerInterface

```php
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\LogRecord;

public function handle(LogRecord $record): void; // throws LogWriteException on failure
```

### JsonFormatter

```php
use Marko\Log\Formatter\ContextRedactor;
use Marko\Log\Formatter\JsonFormatter;

public function __construct(
    array $redactKeys = ContextRedactor::DEFAULT_KEYS,
    int $maxDepth = 10,
);
```

### LogFormatterInterface

```php
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\LogRecord;

public function format(LogRecord $record): string;
```

### LogLevel

```php
use Marko\Log\LogLevel;

public function severity(): int;
public function meetsThreshold(LogLevel $minimum): bool;
public function upperName(): string;
```

### LogRecord

```php
use Marko\Log\LogRecord;
use Marko\Log\LogLevel;
use DateTimeImmutable;

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

### LogConfig

```php
use Marko\Log\Config\LogConfig;
use Marko\Log\LogLevel;

public function handlers(): array; // log.handlers resolved through log.handler_map: name => class
public function formatter(): string; // log.formatter: 'line' or 'json'
public function path(): string;
public function level(): LogLevel;
public function channel(): string;
public function format(): string;
public function dateFormat(): string;
public function maxFiles(): int;
public function maxFileSize(): int;
public function escapeNewlines(): bool; // log.escape_newlines, default true
public function redactKeys(): array; // log.redact_keys, list of context keys to mask
public function fileMode(): int; // log.file_mode, default 0600
public function dirMode(): int; // log.dir_mode, default 0700
```

`handlers()` throws `InvalidLogConfigException` for an empty list, an unknown or duplicate name, or a map entry that does not implement `LogHandlerInterface`, and `NoDriverException` when no handler package is installed. `redactKeys()` throws `InvalidLogConfigException` for an entry that is not a non-empty string; `fileMode()` and `dirMode()` throw it for a mode outside `0`--`0777`.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `LogException` | Base exception for all log errors --- includes `getContext()` and `getSuggestion()` methods |
| `InvalidLogLevelException` | Thrown when a log level string does not match any valid level |
| `InvalidLogConfigException` | Thrown when `log.handlers`, `log.handler_map`, `log.formatter`, `log.redact_keys`, `log.file_mode` or `log.dir_mode` holds an invalid value |
| `LogWriteException` | Thrown when a handler cannot write a record (file permissions, a closed stream) |
| `NoDriverException` | Thrown when `LoggerInterface` is resolved but no handler package is installed |

## Related Packages

- [`marko/log-file`](/docs/packages/log-file/) --- the `file` handler, with rotation
- [`marko/log-stream`](/docs/packages/log-stream/) --- the `stream` handler (stderr/stdout), for containers
- [`marko/testing`](/docs/packages/testing/) --- `FakeLogger` for assertions in tests
