# Marko Log File

File-based logging driver--writes log messages to disk with daily or size-based rotation.

## Overview

This package implements `LoggerInterface` by writing formatted log lines to files. It supports daily rotation (one file per day) and size-based rotation (new file when size limit is reached). Messages below the configured minimum level are silently skipped. All settings come from `config/log.php`.

## Installation

```bash
composer require marko/log-file
```

## Usage

### Automatic via Binding

Bind the logger interface in your `module.php`:

```php
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Driver\FileLogger;

return [
    'bindings' => [
        LoggerInterface::class => FileLogger::class,
    ],
];
```

Then inject `LoggerInterface` anywhere:

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

**Daily rotation** (default) -- one file per day, date in filename:

```
var/log/app-2025-01-15.log
var/log/app-2025-01-16.log
```

**Size rotation** -- rotates when a file exceeds the configured size limit:

```
var/log/app.log
var/log/app.1.log
var/log/app.2.log
```

## Customization

Replace the rotation strategy via Preference:

```php
use Marko\Core\Attributes\Preference;
use Marko\Log\File\Rotation\RotationStrategyInterface;
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

### FileLogger

Implements `LoggerInterface`. See `marko/log` for the full method list.

```php
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

### RotationStrategyInterface

```php
interface RotationStrategyInterface
{
    public function getCurrentPath(string $basePath, string $channel): string;
    public function needsRotation(string $filePath): bool;
}
```

### Built-in Rotation Strategies

- `DailyRotation` -- Date-stamped filenames, rotates automatically each day
- `SizeRotation` -- Numbered filenames, rotates when file exceeds max size (default: 10 MB)
