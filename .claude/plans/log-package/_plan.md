# Plan: Log Package

## Created
2026-01-21

## Status
pending

## Objective
Implement the logging layer for Marko framework with PSR-3 compatible interface and file-based driver, following the established interface/implementation split pattern.

## Scope

### In Scope
- `marko/log` package with interfaces, log levels, configuration, and exceptions
  - `LoggerInterface` - PSR-3 compatible logging contract (emergency, alert, critical, error, warning, notice, info, debug, log)
  - `LogLevel` enum - Standard PSR-3 log levels
  - `LogConfig` - Configuration loaded from config/log.php
  - `LogRecord` - Value object for log entries (level, message, context, timestamp, channel)
  - `LogFormatterInterface` - Contract for formatting log records
  - `LineFormatter` - Default formatter (timestamp, level, channel, message, context)
  - `LogException` hierarchy (LogException, InvalidLogLevelException, LogWriteException)
  - CLI command: `log:clear` (clear old log files)
- `marko/log-file` package with file-based logging driver
  - `FileLogger` - Implements LoggerInterface using filesystem storage
  - `FileLoggerFactory` - Factory for creating logger with config
  - Configurable log directory (default: `storage/logs`)
  - Daily file rotation (e.g., `app-2026-01-21.log`)
  - Size-based rotation (configurable max file size)
  - Atomic write operations for safety

### Out of Scope
- Database logging driver (future package: `marko/log-database`)
- Syslog driver (future package: `marko/log-syslog`)
- External service drivers (Loggly, Papertrail, etc.)
- Log aggregation and search
- Real-time log streaming
- Log-based alerting
- Structured logging with metrics

## Success Criteria
- [ ] `LoggerInterface` is PSR-3 compatible with all 8 log level methods plus `log()`
- [ ] `LogLevel` enum provides type-safe log levels with severity ordering
- [ ] `LogConfig` loads configuration from `config/log.php`
- [ ] `LogRecord` encapsulates log entry data immutably
- [ ] `LineFormatter` produces human-readable log lines with configurable format
- [ ] `FileLogger` implements all logging operations using filesystem
- [ ] Daily rotation creates new file each day (e.g., `app-2026-01-21.log`)
- [ ] Size rotation creates new file when max size exceeded
- [ ] `log:clear` clears old log files with configurable retention (days)
- [ ] Contextual logging interpolates placeholders `{key}` with context values
- [ ] Loud error when no logger driver is installed
- [ ] Driver conflict handling if multiple drivers installed
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json for log, log-file) | - | pending |
| 002 | LogException hierarchy | 001 | pending |
| 003 | LogLevel enum | 001 | pending |
| 004 | LogRecord value object | 003 | pending |
| 005 | LogFormatterInterface and LineFormatter | 004 | pending |
| 006 | LoggerInterface contract | 003, 004 | pending |
| 007 | LogConfig class | 001 | pending |
| 008 | log package module.php with LogConfig binding | 007 | pending |
| 009 | FileLogger implementation | 005, 006 | pending |
| 010 | File rotation logic (daily + size) | 009 | pending |
| 011 | FileLoggerFactory | 007, 010 | pending |
| 012 | log-file module.php with bindings | 011 | pending |
| 013 | CLI: log:clear command | 006 | pending |
| 014 | Unit tests for log package | 002-006 | pending |
| 015 | Unit tests for log-file package | 009-011 | pending |
| 016 | Integration tests | 012, 013 | pending |

## Architecture Notes

### Package Structure
```
packages/
  log/                        # Interfaces + shared code
    src/
      Contracts/
        LoggerInterface.php
        LogFormatterInterface.php
      Config/
        LogConfig.php
      Exceptions/
        LogException.php
        InvalidLogLevelException.php
        LogWriteException.php
      LogLevel.php            # Enum
      LogRecord.php           # Value object
      Formatter/
        LineFormatter.php
      Command/
        ClearCommand.php
    tests/
    composer.json
    module.php
  log-file/                   # File-based implementation
    src/
      Driver/
        FileLogger.php
      Factory/
        FileLoggerFactory.php
      Rotation/
        DailyRotation.php
        SizeRotation.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/log.php
return [
    'driver' => 'file',
    'path' => $_ENV['LOG_PATH'] ?? 'storage/logs',
    'level' => $_ENV['LOG_LEVEL'] ?? 'debug',       // Minimum level to log
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'app',     // Default channel name
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => 30,                              // Days to keep for rotation
    'max_file_size' => 10 * 1024 * 1024,           // 10MB for size rotation
];
```

### PSR-3 Compatible LoggerInterface
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

### LogLevel Enum
```php
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert => 1,
            self::Critical => 2,
            self::Error => 3,
            self::Warning => 4,
            self::Notice => 5,
            self::Info => 6,
            self::Debug => 7,
        };
    }

    public function meetsThreshold(LogLevel $minimum): bool
    {
        return $this->severity() <= $minimum->severity();
    }
}
```

### LogRecord Value Object
```php
readonly class LogRecord
{
    public function __construct(
        public LogLevel $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $datetime,
        public string $channel,
    ) {}

    /**
     * Interpolate context values into message placeholders.
     * PSR-3 style: {key} is replaced with context['key']
     */
    public function interpolatedMessage(): string;
}
```

### LineFormatter
```php
readonly class LineFormatter implements LogFormatterInterface
{
    public function __construct(
        private string $format = '[{datetime}] {channel}.{level}: {message} {context}',
        private string $dateFormat = 'Y-m-d H:i:s',
    ) {}

    public function format(LogRecord $record): string;
}
```

### Example Log Output
```
[2026-01-21 10:30:45] app.INFO: User logged in {"user_id":42,"ip":"192.168.1.1"}
[2026-01-21 10:30:46] app.DEBUG: Loading user preferences {"user_id":42}
[2026-01-21 10:31:00] app.ERROR: Failed to process payment {"order_id":123,"error":"Card declined"}
[2026-01-21 10:31:01] app.CRITICAL: Database connection lost {"host":"db.example.com"}
```

### FileLogger Implementation
- Checks minimum log level threshold before processing
- Creates log directory if it doesn't exist
- Rotates files based on date or size
- Uses FILE_APPEND | LOCK_EX for atomic writes
- Lazy file handle opening for performance

### Daily Rotation
Files named: `{channel}-{date}.log` (e.g., `app-2026-01-21.log`)
- New file created at midnight
- Old files cleaned up by `log:clear` command

### CLI Command: log:clear
```
$ marko log:clear
Deleted 15 log file(s) older than 30 days.

$ marko log:clear --days=7
Deleted 23 log file(s) older than 7 days.
```

### Driver Conflict Handling
```
BindingConflictException: Multiple implementations bound for LoggerInterface.

Context: Both FileLogger and DatabaseLogger are attempting to bind.

Suggestion: Install only one log driver package. Remove one with:
  composer remove marko/log-file
  or
  composer remove marko/log-database
```

### No Driver Installed Handling
```
LogException: No log driver installed.

Context: Attempted to resolve LoggerInterface but no implementation is bound.

Suggestion: Install a log driver package:
  composer require marko/log-file
```

### Module Bindings

**log/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        LogConfig::class => LogConfig::class,
        LogFormatterInterface::class => LineFormatter::class,
    ],
];
```

**log-file/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
            return $container->get(FileLoggerFactory::class)->create();
        },
    ],
];
```

### Usage Examples

**Basic Logging:**
```php
class OrderService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function processOrder(Order $order): void
    {
        $this->logger->info('Processing order', [
            'order_id' => $order->id,
            'total' => $order->total,
        ]);

        try {
            // Process...
            $this->logger->debug('Payment processed', ['order_id' => $order->id]);
        } catch (PaymentException $e) {
            $this->logger->error('Payment failed: {error}', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

**Contextual Placeholders (PSR-3 style):**
```php
// Context values are interpolated into {placeholders}
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john_doe',
    'ip' => '192.168.1.100',
]);
// Output: User john_doe logged in from 192.168.1.100
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Filesystem permissions** | Clear error messages when log directory is not writable with suggestion to check permissions |
| **Disk space exhaustion** | Daily rotation with configurable max_files; log:clear command; applications can implement size-based rotation |
| **Concurrent writes** | Use FILE_APPEND | LOCK_EX for atomic appends; no file locking needed for rotation |
| **Large context arrays** | JSON encode context; document that large objects should be logged selectively |
| **PSR-3 compatibility** | Interface matches PSR-3 exactly; use LogLevel enum for type safety while maintaining compatibility |
| **Performance overhead** | Lazy file handle opening; level threshold check before any processing |
| **Log file corruption** | Atomic writes; each log entry is a complete line ending with newline |
