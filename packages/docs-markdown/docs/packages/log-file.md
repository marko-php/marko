---
title: marko/log-file
description: File-based logging driver --- writes log messages to disk with daily or size-based rotation.
---

File-based logging driver --- writes log messages to disk with daily or size-based rotation. This package provides `FileLogHandler`, a [`marko/log`](/docs/packages/log/) handler that appends formatted records to files. It supports daily rotation (one file per day) and size-based rotation (new file when the size limit is reached). The shared `Logger` in `marko/log` applies the level threshold and builds each record; this package only writes it. All settings come from `config/log.php`.

## Installation

```bash
composer require marko/log-file
```

This automatically installs `marko/log`.

## Configuration

The package adds the `file` handler to `log.handler_map`, which makes `file` a valid name in `LOG_HANDLERS`. `file` is the default, so installing the package is enough:

```php title="config/log.php"
<?php

declare(strict_types=1);

use Marko\Log\File\Handler\FileLogHandler;

return [
    'handler_map' => [
        'file' => FileLogHandler::class,
    ],
];
```

The handler reads these [`marko/log` settings](/docs/packages/log/#configuration):

| Key | Env | Default | Description |
|---|---|---|---|
| `path` | `LOG_PATH` | `storage/logs` | Directory where log files are written |
| `max_file_size` | `LOG_MAX_FILE_SIZE` | `10485760` | Size limit for `SizeRotation` (10 MB) |
| `file_mode` | --- | `0600` | Mode applied to newly created log files |
| `dir_mode` | --- | `0700` | Mode applied to a newly created log directory |

To write files and stream to stderr at the same time, install [`marko/log-stream`](/docs/packages/log-stream/) too and set `LOG_HANDLERS=file,stream`. In containers, prefer `LOG_HANDLERS=stream`: files inside a container fill its ephemeral disk, and nothing collects them.

## Usage

Inject `LoggerInterface` anywhere:

```php
use Marko\Log\Contracts\LoggerInterface;

class ImportService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function import(
        string $file,
    ): void {
        $this->logger->info('Starting import', ['file' => $file]);
        // ...
        $this->logger->info('Import complete', ['rows' => 1500]);
    }
}
```

Log output (with daily rotation): `var/log/app-2025-01-15.log`

```
[2025-01-15 10:30:00] app.INFO: Starting import {"file":"products.csv"}
[2025-01-15 10:30:05] app.INFO: Import complete {"rows":1500}
```

### Rotation Strategies

**Daily rotation** (default) --- one file per day, date in filename:

```
var/log/app-2025-01-15.log
var/log/app-2025-01-16.log
```

**Size rotation** --- rotates when a file exceeds the configured size limit (default: 10 MB):

```
var/log/app.log
var/log/app.1.log
var/log/app.2.log
```

### Time and Testing

The `Logger` stamps each record, and `DailyRotation` picks the day's file, from the PSR-20 `Psr\Clock\ClockInterface` bound by [`marko/clock`](/docs/packages/clock/), never the system time directly. Freeze it with [`FakeClock`](/docs/packages/testing/#fakeclock) to test record times and the midnight rollover exactly:

```php
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-01-21 23:59:59');
$logger = new Logger(
    handlers: [new FileLogHandler(
        path: $dir,
        formatter: new LineFormatter(),
        rotation: new DailyRotation($clock),
    )],
    channel: 'app',
    minimumLevel: LogLevel::Debug,
    clock: $clock,
);

$logger->info('Before midnight'); // app-2026-01-21.log, [2026-01-21 23:59:59]
$clock->travel('+1 second');
$logger->info('After midnight');  // app-2026-01-22.log, [2026-01-22 00:00:00]
```

### Upgrading from 0.11

The logger moved into `marko/log`, and this package now provides only the file handler:

- `FileLogger` and `FileLoggerFactory` are removed. `LoggerInterface` is bound by `marko/log` to `Marko\Log\Logger`, which sends records to `FileLogHandler`. Code that constructed `FileLogger` directly builds a `Logger` with a `FileLogHandler`, as in the example above. A Preference or subclass of `FileLogger` moves to `FileLogHandler` (the write step) or `Logger` (threshold and record building).
- This module no longer binds `LoggerInterface`, so remove any `LoggerInterface::class => FileLogger::class` binding from your `module.php`.
- `log.driver` and `LOG_DRIVER` are gone. Use `log.handlers` and `LOG_HANDLERS` (default `file`). A leftover `driver` key in your `config/log.php` is ignored, so delete it.
- `FileLogHandler` names the file after each record's channel, so the constructor no longer takes `$channel`, `$minimumLevel` or `$clock`, and `$rotation` is required.

## Customization

The module binds `RotationStrategyInterface` to `DailyRotation`, resolved by the container, so the handler picks up a replacement. Replace the rotation strategy via Preference:

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\SizeRotation;

#[Preference(replaces: DailyRotation::class)]
class CustomRotation extends SizeRotation
{
    public function __construct()
    {
        parent::__construct(maxSize: 50 * 1024 * 1024); // 50 MB
    }
}
```

## API Reference

### FileLogHandler

Implements `LogHandlerInterface` from [`marko/log`](/docs/packages/log/).

```php
use Marko\Log\LogRecord;

public function handle(LogRecord $record): void;
```

The constructor accepts the following parameters:

| Parameter | Type | Description |
|---|---|---|
| `$path` | `string` | Directory where log files are written (`log.path`) |
| `$formatter` | `LogFormatterInterface` | Formats log records into strings (`log.formatter`) |
| `$rotation` | `RotationStrategyInterface` | Picks the file for each record's channel |
| `$fileMode` | `int` | Mode applied to newly created log files (default `0600`, from `log.file_mode`) |
| `$dirMode` | `int` | Mode applied to a newly created log directory (default `0700`, from `log.dir_mode`) |

Writes use `FILE_APPEND | LOCK_EX` for safe concurrent appends. The log directory is created automatically if it does not exist, with `$dirMode`. Each new log file is created empty and `chmod`-ed to `$fileMode` before the first record is written, so log content is never readable by other local users under a permissive umask. Existing files and directories keep their current permissions. Set `log.file_mode` to `0640` and `log.dir_mode` to `0750` if a log shipper running as a group member needs read access. A `LogWriteException` is thrown if the directory is not writable, the mode cannot be applied, or a write fails.

### RotationStrategyInterface

```php
use Marko\Log\File\Rotation\RotationStrategyInterface;

interface RotationStrategyInterface
{
    public function getCurrentPath(string $basePath, string $channel): string;
    public function needsRotation(string $filePath): bool;
}
```

### Built-in Rotation Strategies

- `DailyRotation` --- Date-stamped filenames from the injected `ClockInterface`, rotates automatically each day
- `SizeRotation` --- Numbered filenames, rotates when file exceeds max size (default: 10 MB)

## Related Packages

- [`marko/log`](/docs/packages/log/) --- the logger, handler contract and formatters
- [`marko/log-stream`](/docs/packages/log-stream/) --- stderr/stdout handler for containers, installable alongside this one
