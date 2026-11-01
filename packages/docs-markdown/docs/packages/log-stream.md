---
title: marko/log-stream
description: Stream logging driver --- writes log records to stderr or stdout for containers and process supervisors.
---

Stream logging driver --- writes log records to stderr or stdout for containers and process supervisors. In Docker, Kubernetes, Fly, Cloud Run, ECS and similar platforms, and under supervisors like systemd, the platform collects, ships and rotates a process's output streams. Writing log files inside a container fills its ephemeral disk instead, and nothing collects them. This package provides `StreamLogHandler`, a [`marko/log`](/docs/packages/log/) handler that writes each formatted record to `php://stderr` (the default) or `php://stdout` with write buffering off. It can be installed next to [`marko/log-file`](/docs/packages/log-file/), and `LOG_HANDLERS` picks which one runs, or stacks both.

## Installation

```bash
composer require marko/log-stream
```

This automatically installs `marko/log`.

## Configuration

The package adds the `stream` handler and its target to `config/log.php` (merged with the [`marko/log` settings](/docs/packages/log/#configuration)):

```php title="config/log.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;
use Marko\Log\Stream\Handler\StreamLogHandler;

return [
    'handler_map' => [
        'stream' => StreamLogHandler::class,
    ],
    'stream' => [
        'target' => Env::string('LOG_STREAM_TARGET', 'php://stderr'),
    ],
];
```

| Key | Env | Default | Description |
|---|---|---|---|
| `stream.target` | `LOG_STREAM_TARGET` | `php://stderr` | `php://stderr` or `php://stdout`. Any other value throws `InvalidLogConfigException` |

Select the handler with `LOG_HANDLERS`:

```bash
# Production container: stderr only, one JSON object per line
LOG_HANDLERS=stream
LOG_FORMATTER=json

# Local development with marko/log-file also installed: write both
LOG_HANDLERS=file,stream
```

## Usage

Inject `LoggerInterface` as usual. Nothing in application code changes when you switch handlers:

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
    }
}
```

With `LOG_FORMATTER=json`, each record is one line on stderr:

```
{"datetime":"2026-01-21T10:30:00.000+00:00","level":"info","channel":"app","message":"Starting import","context":{"file":"products.csv"}}
```

### Containers

- Use `LOG_HANDLERS=stream` so nothing is written to the container's disk.
- Use `LOG_FORMATTER=json` when a collector parses the logs (CloudWatch, Loki, Datadog, GCP Logging and others read one JSON object per line). The default `line` format is easier to read in `docker logs`.
- Under PHP-FPM, a worker's stderr reaches the container output only when the pool sets `catch_workers_output = yes` (and `decorate_workers_output = no` keeps FPM from prefixing each line). The CLI, queue workers and RoadRunner write to the container output directly.

### RoadRunner

RoadRunner carries its worker protocol on stdout, so a worker must not write to it (see [`marko/roadrunner`](/docs/packages/roadrunner/#do-not-write-to-stdout)). When the `RR_MODE` environment variable is set, as it is in every RoadRunner worker, a `php://stdout` target throws `InvalidLogConfigException`. Keep the default `php://stderr`: RoadRunner collects worker stderr into its own logs.

### Testing

Construct the handler directly with a `php://memory` stream. Writes are unbuffered, so the record can be read straight after `handle()`:

```php
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Marko\Log\Stream\Handler\StreamLogHandler;
use Marko\Testing\Fake\FakeClock;

$stream = fopen('php://memory', 'w+');
$logger = new Logger(
    handlers: [new StreamLogHandler($stream, 'php://memory', new LineFormatter())],
    channel: 'app',
    minimumLevel: LogLevel::Debug,
    clock: new FakeClock('2026-01-21 10:30:00'),
);

$logger->info('Hello');
rewind($stream);

stream_get_contents($stream); // "[2026-01-21 10:30:00] app.INFO: Hello\n"
```

To assert on what a service logged, use [`FakeLogger`](/docs/packages/testing/) instead.

## Errors

| Exception | When |
|---|---|
| `InvalidStreamTargetException` (extends `InvalidLogConfigException`) | `log.stream.target` is not `php://stderr` or `php://stdout`, or it is `php://stdout` while `RR_MODE` is set |
| `LogWriteException` | A write fails (for example a closed pipe). The message names the stream |

## API Reference

### StreamLogHandler

Implements `LogHandlerInterface` from [`marko/log`](/docs/packages/log/).

```php
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\LogRecord;

public function __construct(mixed $stream, string $name, LogFormatterInterface $formatter);
public function handle(LogRecord $record): void;
```

| Parameter | Type | Description |
|---|---|---|
| `$stream` | `resource` | An open, writable stream. Write buffering is turned off on construction |
| `$name` | `string` | The stream's name, used in `LogWriteException` messages |
| `$formatter` | `LogFormatterInterface` | Formats each record (`LineFormatter` or `JsonFormatter`, from `log.formatter`) |

Each record is written with one `fwrite` call. The handler only writes again to finish a partial write to a pipe.

### StreamLogHandlerFactory

Builds the handler the module binds: reads `log.stream.target`, validates it, and opens the stream.

```php
public function create(): StreamLogHandler;
```

## Related Packages

- [`marko/log`](/docs/packages/log/) --- the logger, handler contract and formatters
- [`marko/log-file`](/docs/packages/log-file/) --- the file handler, with rotation
- [`marko/roadrunner`](/docs/packages/roadrunner/) --- long-running workers (stderr only)
